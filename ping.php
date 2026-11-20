<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status' => 'ok', 'service' => 'SoporteTecnico IESTP Utcubamba 2026', 'timestamp' => date('c')]);
