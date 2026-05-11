CREATE TABLE IF NOT EXISTS sog5_energy_logs_raw (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_datetime DATETIME NOT NULL,
    e_total_kwh DECIMAL(12,3),
    e_l1_reactive_ind_kvarh DECIMAL(12,3),
    e_l2_reactive_ind_kvarh DECIMAL(12,3),
    e_l3_reactive_ind_kvarh DECIMAL(12,3),
    e_l1_reactive_cap_kvarh DECIMAL(12,3),
    e_l2_reactive_cap_kvarh DECIMAL(12,3),
    e_l3_reactive_cap_kvarh DECIMAL(12,3),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_datetime (log_datetime)
)