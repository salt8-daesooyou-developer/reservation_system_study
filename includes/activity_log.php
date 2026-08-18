<?php
function log_activity(PDO $pdo, int $customerId, string $serviceType, string $actionType, string $description, ?int $staffId): void {
    $stmt = $pdo->prepare('
        INSERT INTO activity_logs (customer_id, service_type, action_type, description, created_by)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([$customerId, $serviceType, $actionType, $description, $staffId]);
}
