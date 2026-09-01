<?php

namespace App\Controllers;

use App\Models\Member;

final class MembersController extends Controller
{
    public function index(): void
    {
        if (isset($_GET['ajax_get_member'])) {
            $this->jsonExit(Member::find((int)$_GET['id']) ?: []);
        }
        if (isset($_GET['ajax_member_history'])) {
            $this->jsonExit(Member::history((int)$_GET['id']));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if (isset($_POST['save_member'])) {
                    Member::create((string)$_POST['name'], (string)$_POST['phone'], (string)$_POST['email']);
                    $this->flash('success', 'Member Registered Successfully!');
                }
                if (isset($_POST['update_member'])) {
                    Member::update((int)$_POST['member_id'], (string)$_POST['name'], (string)$_POST['phone'], (string)$_POST['email']);
                    $this->flash('success', 'Member Updated!');
                }
            } catch (\PDOException $e) {
                $this->flash('error', isset($_POST['save_member']) ? 'Error: Phone number may already exist.' : 'Error updating member.');
            }
            $this->redirect('members');
        }

        $this->render(['members' => Member::all()]);
    }
}
