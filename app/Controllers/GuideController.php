<?php

namespace App\Controllers;

use App\Models\Setting;
use App\Services\Guide;

final class GuideController extends Controller
{
    private const MAX_QUESTION = 400;
    private const MAX_PER_HOUR = 20;

    public function index(): void
    {
        if (strcasecmp((string)($_SERVER['REQUEST_METHOD'] ?? ''), 'POST') !== 0) {
            $this->jsonExit(['status' => 'error', 'msg' => 'Send a question.'], 405);
        }

        $question = trim((string)($_POST['question'] ?? ''));
        if ($question === '') {
            $this->jsonExit(['status' => 'error', 'msg' => 'Type a question.'], 400);
        }
        if (strlen($question) > self::MAX_QUESTION) {
            $this->jsonExit(['status' => 'error', 'msg' => 'Keep the question under 400 characters.'], 400);
        }

        $settings = Setting::all();
        if (!Guide::ready($settings)) {
            $this->jsonExit([
                'status' => 'setup',
                'msg' => 'A manager still needs to save a Gemini key under System Settings, Guide.',
            ]);
        }

        $now = time();
        $times = [];
        foreach ((array)($_SESSION['guide_times'] ?? []) as $stamp) {
            $stamp = (int)$stamp;
            if ($now - $stamp < 3600) {
                $times[] = $stamp;
            }
        }
        if (count($times) >= self::MAX_PER_HOUR) {
            $this->jsonExit(['status' => 'error', 'msg' => 'This session has asked enough questions for now. Try again later.'], 429);
        }
        $times[] = $now;
        $_SESSION['guide_times'] = $times;

        $history = json_decode((string)($_POST['history'] ?? '[]'), true);
        if (!is_array($history)) {
            $history = [];
        }

        try {
            $presented = (new Guide())->answer($settings, $question, $history, $this->role());
        } catch (\Throwable $e) {
            $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage() ?: 'The guide could not answer.'], 502);
        }

        $this->jsonExit([
            'status' => 'ok',
            'answer' => $presented['text'],
            'html' => $presented['html'],
            'links' => $presented['links'],
        ]);
    }
}
