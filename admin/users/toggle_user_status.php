<?php
include __DIR__ . '/../../database/database.php';

if(isset($_GET['id'])){

    $id = $_GET['id'];

    $stmt = $conn->prepare("SELECT status FROM tab1 WHERE employee_id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if($user['status'] == 'Active'){
        $new_status = 'Inactive';
    }else{
        $new_status = 'Active';
    }

    $stmt = $conn->prepare("UPDATE tab1 SET status=? WHERE employee_id=?");
    $stmt->execute([$new_status,$id]);

    header("Location: users.php");

}
?>
<?php
include '../database/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: users.php');
    exit;
}

try {
    $stmt = $conn->prepare("SELECT status FROM tab1 WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: users.php');
        exit;
    }

    $newStatus = ($user['status'] === 'Active') ? 'Inactive' : 'Active';

    $upd = $conn->prepare("UPDATE tab1 SET status = ? WHERE id = ?");
    $upd->execute([$newStatus, $id]);

} catch (Exception $e) {
    error_log('toggle_user_status error: ' . $e->getMessage());
}

header('Location: users.php');
exit;
?>
<?php
include '../database/database.php';
?>