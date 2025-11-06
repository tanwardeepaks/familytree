<?php
session_start();
include '../db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Remove spouse references
        $conn->query("UPDATE family_members SET spouse_id = NULL WHERE spouse_id = $id");
        
        // Remove parent references
        $conn->query("UPDATE family_members SET father_id = NULL WHERE father_id = $id");
        $conn->query("UPDATE family_members SET mother_id = NULL WHERE mother_id = $id");
        
        // Delete the member
        $conn->query("DELETE FROM family_members WHERE id = $id");
        
        // Commit transaction
        $conn->commit();
        
        header('Location: dashboard.php');
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        die("Error deleting member: " . $e->getMessage());
    }
}

header('Location: dashboard.php');
?>