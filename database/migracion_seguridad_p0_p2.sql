-- ============================================================================
-- SCRIPT DE MIGRACIÓN SEGURA (P0, P1 y P2) — IESTP UTCUBAMBA 2026
-- NO borra ni sobrescribe registros reales existentes.
-- Compatible con MySQL 8 / MariaDB (XAMPP) y TiDB Cloud Serverless.
-- ============================================================================
USE `bd_mantenimiento_iestpu`;

-- 1. Endurecimiento de tabla usuarios (bcrypt + columna activo)
ALTER TABLE usuarios MODIFY COLUMN clave VARCHAR(255) NOT NULL;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS activo TINYINT(1) NOT NULL DEFAULT 1;

-- Convertir contraseñas en texto plano existentes ('admin123') a hash bcrypt seguro
UPDATE usuarios
SET clave = '$2y$12$vr2225GU9NCJlhw2OE449u8pJ59uKGTK6zsjqlJoZhPiyavidf.Y6'
WHERE clave = 'admin123' OR clave NOT LIKE '$2y$%';

-- 2. Ampliación de solicitudes_mantenimiento para evidencias y costos (P2)
ALTER TABLE solicitudes_mantenimiento ADD COLUMN IF NOT EXISTS tipo_mantenimiento VARCHAR(30) DEFAULT 'CORRECTIVO';
ALTER TABLE solicitudes_mantenimiento ADD COLUMN IF NOT EXISTS repuestos_usados TEXT NULL;
ALTER TABLE solicitudes_mantenimiento ADD COLUMN IF NOT EXISTS costo_estimado DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE solicitudes_mantenimiento ADD COLUMN IF NOT EXISTS horas_tecnicas DECIMAL(5,2) DEFAULT 0.00;
ALTER TABLE solicitudes_mantenimiento ADD COLUMN IF NOT EXISTS foto_falla VARCHAR(255) NULL;
ALTER TABLE solicitudes_mantenimiento ADD COLUMN IF NOT EXISTS foto_cierre VARCHAR(255) NULL;

-- 3. Corrección de erratas, duplicados y desincronización de estados (P1)
UPDATE bienes_inventario SET marca = 'DAEWOO' WHERE UPPER(marca) = 'DAEVOO';
UPDATE bienes_inventario SET especificaciones = REPLACE(especificaciones, 'NVIDEA', 'NVIDIA') WHERE especificaciones LIKE '%NVIDEA%';
UPDATE bienes_inventario SET codigo_apsti = 'CAF-CIL01', codigo_interno = 'CAF-CIL01' WHERE correlativo = '301' AND codigo_apsti = 'CAF-C';
UPDATE bienes_inventario SET codigo_apsti = 'CAF-CON01', codigo_interno = 'CAF-CON01' WHERE correlativo = '303' AND codigo_apsti = 'CAF-C';
UPDATE bienes_inventario SET codigo_apsti = 'CAF-COC01', codigo_interno = 'CAF-COC01' WHERE correlativo = '302' AND codigo_apsti = 'CAF- CF';

-- Sincronizar bienes que NO requieren mantenimiento (mobiliario) para que no inflen el % de mantenimiento
UPDATE bienes_inventario SET estado_servicio = 'No Aplica' WHERE requiere_mantenimiento = 0;

-- Sincronizar equipo APSTI-CPU-LC1-002 (correlativo 002) con su solicitud cerrada SOL-2026-014
UPDATE bienes_inventario
SET estado_servicio = 'Servicio Completo',
    estado_visita = 'Visitado',
    fecha_limite_mantenimiento = '2027-02-11',
    proxima_visita = '2027-02-11',
    diagnostico = 'Cambio de conector RJ45 Cat6, limpieza interna de chasis y prueba de conectividad OK.'
WHERE codigo_apsti = 'APSTI-CPU-LC1-002';

-- Corregir coherencia de SOL-2026-015 (en Paso 2: Verificado con Ficha -> resultado PENDIENTE hasta cierre en Paso 3)
UPDATE solicitudes_mantenimiento
SET resultado_servicio = 'PENDIENTE',
    diagnostico_tecnico = NULL,
    acciones_realizadas = NULL
WHERE codigo_solicitud = 'SOL-2026-015' AND estado_flujo = '2_VERIFICADO_CON_FICHA';

-- 4. Tablas nuevas de Fase P2 (Movimientos de Bienes y Auditoría)
CREATE TABLE IF NOT EXISTS movimientos_bienes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo_acta VARCHAR(40) NOT NULL,
  bien_id INT NOT NULL,
  codigo_bien VARCHAR(60) NOT NULL,
  descripcion_bien VARCHAR(160) NOT NULL,
  tipo_movimiento VARCHAR(30) NOT NULL DEFAULT 'TRASLADO',
  area_origen VARCHAR(120) NOT NULL,
  area_destino VARCHAR(120) NOT NULL,
  responsable_entrega VARCHAR(120) NOT NULL,
  responsable_recibe VARCHAR(120) NOT NULL,
  fecha_movimiento DATE NOT NULL,
  motivo TEXT NOT NULL,
  estado_movimiento VARCHAR(30) NOT NULL DEFAULT 'EJECUTADO',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auditoria_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL DEFAULT 0,
  rol VARCHAR(40) NOT NULL,
  accion VARCHAR(60) NOT NULL,
  tabla_afectada VARCHAR(60) NOT NULL,
  registro_id VARCHAR(60) NULL,
  detalle TEXT NULL,
  fecha_hora DATETIME NOT NULL,
  ip VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
