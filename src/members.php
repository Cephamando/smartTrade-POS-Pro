<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

// --- AJAX: GET MEMBER DETAILS (For Edit) ---
if (isset($_GET['ajax_get_member'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit;
}

// --- AJAX: GET MEMBER HISTORY ---
if (isset($_GET['ajax_member_history'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("
        SELECT s.*, u.username as cashier 
        FROM sales s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.member_id = ? 
        ORDER BY s.created_at DESC LIMIT 20
    ");
    $stmt->execute([$id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($history);
    exit;
}

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD NEW MEMBER
    if (isset($_POST['save_member'])) {
        $name = $_POST['name'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO members (name, phone, email) VALUES (?, ?, ?)");
            $stmt->execute([$name, $phone, $email]);
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Member Registered Successfully!";
        } catch (PDOException $e) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Error: Phone number may already exist.";
        }
    }

    // 2. UPDATE EXISTING MEMBER
    if (isset($_POST['update_member'])) {
        $id = $_POST['member_id'];
        $name = $_POST['name'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];

        try {
            $stmt = $pdo->prepare("UPDATE members SET name = ?, phone = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $email, $id]);
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Member Updated!";
        } catch (PDOException $e) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Error updating member.";
        }
    }
    
    header("Location: index.php?page=members"); exit;
}

// FETCH LIST
$members = $pdo->query("SELECT * FROM members ORDER BY name ASC")->fetchAll();
?>
