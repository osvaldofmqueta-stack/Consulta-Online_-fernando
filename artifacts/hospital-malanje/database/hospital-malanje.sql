-- Hospital de Malanje
-- Esquema local MySQL/MariaDB para instalação com XAMPP ou WAMP.
-- O ficheiro é idempotente: pode ser importado mais de uma vez sem apagar dados.

SET NAMES utf8mb4;
-- As foreign keys ficam temporariamente suspensas para permitir importação
-- idempotente da estrutura em instalações que já têm dados relacionados.
SET FOREIGN_KEY_CHECKS = 0;

-- Departamentos usados para organizar médicos e marcações.
CREATE TABLE IF NOT EXISTS departamentos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    color VARCHAR(20) NOT NULL DEFAULT '#2b7a78',
    active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_departamentos_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Processo clínico e dados básicos do paciente. patient_type é classificação
-- administrativa separada do papel de acesso da conta.
CREATE TABLE IF NOT EXISTS pacientes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    medical_record_number VARCHAR(80) NOT NULL,
    name VARCHAR(190) NOT NULL,
    sex VARCHAR(30) NOT NULL,
    birth_date VARCHAR(10) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    neighborhood VARCHAR(160) NULL,
    patient_type VARCHAR(30) NOT NULL DEFAULT 'general',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pacientes_record (medical_record_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Médicos pertencem a um departamento e podem ser associados a uma conta.
CREATE TABLE IF NOT EXISTS medicos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    specialty VARCHAR(160) NOT NULL,
    department_id INT UNSIGNED NOT NULL,
    initials VARCHAR(12) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_medicos_department (department_id),
    CONSTRAINT fk_medicos_departamentos FOREIGN KEY (department_id) REFERENCES departamentos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contas de login. patient_id e doctor_id ligam cada papel ao seu registo.
CREATE TABLE IF NOT EXISTS contas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'patient',
    patient_id INT UNSIGNED NULL,
    doctor_id INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    phone VARCHAR(40) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contas_email (email),
    KEY idx_contas_patient (patient_id),
    KEY idx_contas_doctor (doctor_id),
    CONSTRAINT fk_contas_pacientes FOREIGN KEY (patient_id) REFERENCES pacientes(id) ON DELETE SET NULL,
    CONSTRAINT fk_contas_medicos FOREIGN KEY (doctor_id) REFERENCES medicos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens de recuperação são guardados como hash e expiram/consomem-se uma vez.
CREATE TABLE IF NOT EXISTS tokens_recuperacao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tokens_hash (token_hash),
    KEY idx_tokens_account (account_id),
    CONSTRAINT fk_tokens_contas FOREIGN KEY (account_id) REFERENCES contas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agenda de consultas com estado, profissional, departamento e paciente.
CREATE TABLE IF NOT EXISTS consultas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    department_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    time VARCHAR(10) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'scheduled',
    type VARCHAR(40) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_consultas_patient (patient_id),
    KEY idx_consultas_doctor_date (doctor_id, date, time),
    CONSTRAINT fk_consultas_pacientes FOREIGN KEY (patient_id) REFERENCES pacientes(id),
    CONSTRAINT fk_consultas_departamentos FOREIGN KEY (department_id) REFERENCES departamentos(id),
    CONSTRAINT fk_consultas_medicos FOREIGN KEY (doctor_id) REFERENCES medicos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversas do paciente com a equipa; doctor_id pode ficar nulo para a caixa geral.
CREATE TABLE IF NOT EXISTS mensagens_pacientes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NULL,
    sender_clerk_user_id VARCHAR(100) NOT NULL,
    sender_role VARCHAR(30) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    KEY idx_mensagens_patient (patient_id),
    KEY idx_mensagens_doctor (doctor_id),
    CONSTRAINT fk_mensagens_pacientes FOREIGN KEY (patient_id) REFERENCES pacientes(id),
    CONSTRAINT fk_mensagens_medicos FOREIGN KEY (doctor_id) REFERENCES medicos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documentos privados: o conteúdo é binário e o acesso é sempre filtrado pelo backend.
CREATE TABLE IF NOT EXISTS documentos_pacientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    uploaded_by_account_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'document',
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    file_content LONGBLOB NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_documentos_patient (patient_id),
    CONSTRAINT fk_documentos_pacientes FOREIGN KEY (patient_id) REFERENCES pacientes(id),
    CONSTRAINT fk_documentos_contas FOREIGN KEY (uploaded_by_account_id) REFERENCES contas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Receitas podem estar ligadas a uma consulta, mas continuam no histórico do paciente.
CREATE TABLE IF NOT EXISTS receitas_pacientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NULL,
    appointment_id INT UNSIGNED NULL,
    medication VARCHAR(190) NOT NULL,
    dosage VARCHAR(190) NOT NULL,
    frequency VARCHAR(190) NOT NULL,
    duration VARCHAR(190) NOT NULL,
    instructions TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_receitas_patient (patient_id),
    CONSTRAINT fk_receitas_pacientes FOREIGN KEY (patient_id) REFERENCES pacientes(id),
    CONSTRAINT fk_receitas_medicos FOREIGN KEY (doctor_id) REFERENCES medicos(id) ON DELETE SET NULL,
    CONSTRAINT fk_receitas_consultas FOREIGN KEY (appointment_id) REFERENCES consultas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resultados clínicos publicados, também opcionalmente ligados à consulta de origem.
CREATE TABLE IF NOT EXISTS resultados_pacientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NULL,
    appointment_id INT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    result_text TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'published',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_resultados_patient (patient_id),
    CONSTRAINT fk_resultados_pacientes FOREIGN KEY (patient_id) REFERENCES pacientes(id),
    CONSTRAINT fk_resultados_medicos FOREIGN KEY (doctor_id) REFERENCES medicos(id) ON DELETE SET NULL,
    CONSTRAINT fk_resultados_consultas FOREIGN KEY (appointment_id) REFERENCES consultas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Definições editáveis do hospital, auditoria clínica e actividade operacional.
CREATE TABLE IF NOT EXISTS definicoes_hospital (
    setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eventos_auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(120) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id VARCHAR(80) NULL,
    detail TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_auditoria_account (account_id),
    CONSTRAINT fk_auditoria_contas FOREIGN KEY (account_id) REFERENCES contas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS atividade (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    actor VARCHAR(190) NULL,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO departamentos (name, color, active) VALUES
    ('Medicina Geral', '#2b7a78', 1),
    ('Ginecologia', '#a85f2f', 1),
    ('Pediatria', '#5c6ac4', 1);

-- Valores iniciais seguros para uma instalação nova; INSERT IGNORE permite repetir o import.
INSERT IGNORE INTO definicoes_hospital (setting_key, setting_value) VALUES
    ('hospital_name', 'Hospital de Malanje'),
    ('appointment_notice', 'Chegue 15 minutos antes da hora marcada.'),
    ('working_hours', '08:00-17:00');

SET FOREIGN_KEY_CHECKS = 1;