<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Setting;
use App\Services\Guide;
use App\Services\ReportNarrator;

final class AnalysisController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        $narrator = new ReportNarrator();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_analysis'])) {
            unset($_SESSION['analysis_chat']);
            $this->jsonExit(['status' => 'success']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['explain_analysis'])) {
            try {
                $start = (string)($_POST['start'] ?? '');
                $end = (string)($_POST['end'] ?? '');
                $locationId = (string)($_POST['location'] ?? '');
                $picture = $narrator->businessPicture($start, $end, $locationId);
                $question = $narrator->clipQuestion((string)($_POST['question'] ?? ''));
                $chat = $_SESSION['analysis_chat'] ?? null;
                $same = is_array($chat)
                    && ($chat['start'] ?? '') === $start
                    && ($chat['end'] ?? '') === $end
                    && (string)($chat['location'] ?? '') === $locationId;
                $history = $same && is_array($chat['turns'] ?? null) ? $chat['turns'] : [];
                $text = $narrator->discuss(Setting::all(), $picture['brief'], $history, $question);
                if ($question === '') {
                    $history[] = ['role' => 'user', 'text' => 'Summarise this period.'];
                } else {
                    $history[] = ['role' => 'user', 'text' => $question];
                }
                $history[] = ['role' => 'model', 'text' => $text];
                $history = array_slice($history, -6);
                $_SESSION['analysis_chat'] = [
                    'start' => $start,
                    'end' => $end,
                    'location' => $locationId,
                    'turns' => $history,
                ];
                $this->jsonExit(['status' => 'success', 'text' => $text, 'question' => $question]);
            } catch (\Throwable $e) {
                $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage() ?: 'The note could not be written.']);
            }
        }

        $end = (string)($_GET['end'] ?? date('Y-m-d'));
        $start = (string)($_GET['start'] ?? date('Y-m-d', strtotime('-27 days')));
        $locationId = (string)($_GET['location'] ?? '');
        try {
            $picture = $narrator->businessPicture($start, $end, $locationId);
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
            $end = date('Y-m-d');
            $start = date('Y-m-d', strtotime('-27 days'));
            $picture = $narrator->businessPicture($start, $end, '');
            $locationId = '';
        }

        $this->render([
            'start' => $start,
            'end' => $end,
            'locationId' => $locationId,
            'locations' => Location::names(),
            'picture' => $picture,
            'explainReady' => Guide::ready(Setting::all()),
        ]);
    }
}
