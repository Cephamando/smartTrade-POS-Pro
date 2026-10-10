<?php

namespace App\Controllers;

use App\Helpers\Media;
use App\Models\Event;
use App\Models\Location;
use App\Models\Setting;
use App\Services\Mailer;

final class EventsController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        Event::ensureSchema();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }

        $id = (int)($_GET['id'] ?? 0);
        $month = (string)($_GET['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $event = $id ? Event::find($id) : null;
        $this->render([
            'title' => $event ? $event['title'] : 'Events',
            'month' => $month,
            'monthEvents' => Event::inMonth($month),
            'events' => Event::all(),
            'event' => $event,
            'locations' => Location::all(),
            'library' => $event || isset($_GET['new']) ? Media::list() : [],
            'options' => $event ? Event::options($id) : [],
            'guests' => $event ? Event::guests($id) : [],
            'payments' => $event ? Event::payments($id) : [],
            'messages' => $event ? Event::messages($id) : [],
            'tasks' => $event ? Event::tasks($id) : [],
            'agenda' => $event ? Event::agenda($id) : [],
            'acceptedSize' => $event ? Event::acceptedSize($id) : 0,
            'mailReady' => Mailer::ready(Setting::all()),
            'showNew' => isset($_GET['new']),
        ]);
    }

    private function handlePost(): never
    {
        $back = [];
        if (!empty($_POST['event_id'])) {
            $back['id'] = (int)$_POST['event_id'];
        }
        try {
            if (isset($_POST['save_event'])) {
                if (($_POST['cover_framed'] ?? '') === '1') {
                    $upload = $_FILES['cover_upload'] ?? null;
                    $err = is_array($upload) ? (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
                    if ($err !== UPLOAD_ERR_OK) {
                        throw new \RuntimeException('The framed cover did not upload. Try a smaller picture.');
                    }
                    $_POST['cover_path'] = Media::store('cover_upload', 'library');
                }
                $id = Event::save(
                    (int)($_POST['event_id'] ?? 0) ?: null,
                    $_POST + ['created_by' => $this->userId()]
                );
                $this->flash('success', 'Event saved.');
                $this->redirect('events', ['id' => $id]);
            }
            $eventId = (int)($_POST['event_id'] ?? 0);
            $event = Event::find($eventId);
            if (!$event) {
                throw new \RuntimeException('Choose an event first.');
            }
            if (isset($_POST['delete_event'])) {
                Event::delete($eventId);
                $this->flash('success', 'Event removed.');
                $this->redirect('events');
            } elseif (isset($_POST['add_option'])) {
                Event::saveOption($eventId, (string)$_POST['group_name'], (string)$_POST['name'], (float)$_POST['price'], (int)$_POST['capacity']);
                $this->flash('success', 'Choice added.');
            } elseif (isset($_POST['delete_option'])) {
                Event::deleteOption($eventId, (int)$_POST['option_id']);
                $this->flash('success', 'Choice removed.');
            } elseif (isset($_POST['invite_guest'])) {
                $guest = Event::addGuest($eventId, (string)$_POST['name'], (string)$_POST['email'], (string)$_POST['phone'], (int)($_POST['party_size'] ?? 1), 'invited', 'invited');
                $sent = $this->sendOne($event, $guest, (string)($_POST['subject'] ?? ''), (string)($_POST['body'] ?? ''));
                $this->flash('success', $sent ? 'Invitation emailed.' : 'Invitation saved. The link is ready, and the email was not sent.');
            } elseif (isset($_POST['guest_status'])) {
                Event::setGuestStatus($eventId, (int)$_POST['guest_id'], (string)$_POST['guest_status']);
                $this->flash('success', 'Guest updated.');
            } elseif (isset($_POST['check_in'])) {
                $guest = Event::guest((int)$_POST['guest_id']);
                Event::setCheckedIn($eventId, (int)$_POST['guest_id'], $guest && !(int)$guest['checked_in']);
                $this->flash('success', 'Check-in updated.');
            } elseif (isset($_POST['send_mail'])) {
                $this->sendMany($event, (string)$_POST['audience'], (string)$_POST['subject'], (string)$_POST['body']);
            } elseif (isset($_POST['cash_payment'])) {
                Event::addCashPayment($eventId, (int)$_POST['guest_id'], (float)$_POST['amount'], (string)$_POST['purpose']);
                $this->flash('success', 'Cash payment recorded.');
            } elseif (isset($_POST['add_task'])) {
                Event::addTask($eventId, (string)$_POST['title'], (string)($_POST['due_at'] ?? '') ?: null);
                $this->flash('success', 'Task added.');
            } elseif (isset($_POST['toggle_task'])) {
                Event::toggleTask($eventId, (int)$_POST['task_id']);
                $this->flash('success', 'Task updated.');
            } elseif (isset($_POST['add_agenda'])) {
                Event::addAgenda($eventId, (string)$_POST['starts_at'], (string)$_POST['title'], (string)($_POST['notes'] ?? ''));
                $this->flash('success', 'Agenda item added.');
            } elseif (isset($_POST['delete_agenda'])) {
                Event::deleteAgenda($eventId, (int)$_POST['agenda_id']);
                $this->flash('success', 'Agenda item removed.');
            }
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage() ?: 'Unable to save the event.');
        }
        $this->redirect('events', $back);
    }

    private function sendOne(array $event, array $guest, string $subject, string $body): bool
    {
        $subject = trim($subject) !== '' ? trim($subject) : ('You are invited: ' . $event['title']);
        $body = trim($body) !== '' ? $body : $this->defaultBody();
        return $this->deliver($event, $guest, $subject, $body);
    }

    private function sendMany(array $event, string $audience, string $subject, string $body): void
    {
        $allowed = ['invited', 'applied', 'accepted', 'waitlisted', 'all'];
        if (!in_array($audience, $allowed, true)) {
            throw new \RuntimeException('Choose who should receive the message.');
        }
        $subject = trim($subject);
        $body = trim($body);
        if ($subject === '' || $body === '') {
            throw new \RuntimeException('Write a subject and a message.');
        }
        $guests = Event::guestsForMail((int)$event['id'], $audience);
        if (!$guests) {
            throw new \RuntimeException('No guests with an email address match that list.');
        }
        $sent = 0;
        foreach ($guests as $guest) {
            if ($this->deliver($event, $guest, $subject, $body)) {
                $sent++;
            }
        }
        $this->flash('success', $sent . ' of ' . count($guests) . ' messages sent.');
    }

    private function deliver(array $event, array $guest, string $subject, string $body): bool
    {
        $link = $this->absolute('index.php?page=shop&view=rsvp&token=' . $guest['token']);
        $text = strtr($body, [
            '{name}' => (string)$guest['name'],
            '{event}' => (string)$event['title'],
            '{when}' => date('D j M Y, H:i', strtotime((string)$event['starts_at'])),
            '{link}' => $link,
        ]);
        $subject = strtr($subject, ['{event}' => (string)$event['title'], '{name}' => (string)$guest['name']]);
        $settings = Setting::all();
        try {
            Mailer::send($settings, (string)$guest['email'], $subject, $text);
            Event::logMessage((int)$event['id'], (int)$guest['id'], (string)$guest['email'], $subject, $text, 'sent');
            return true;
        } catch (\Throwable $e) {
            Event::logMessage((int)$event['id'], (int)$guest['id'], (string)$guest['email'], $subject, $text, 'failed', $e->getMessage());
            return false;
        }
    }

    private function defaultBody(): string
    {
        return "Hello {name},\n\nYou are invited to {event} on {when}.\nOpen your invitation to reply and choose your options:\n{link}";
    }

    private function absolute(string $path): string
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        return ($https ? 'https' : 'http') . '://' . $host . $base . '/' . ltrim($path, '/');
    }
}
