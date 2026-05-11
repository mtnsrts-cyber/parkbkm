SELECT log_date, e_total_kwh, e_l1_reactive_ind_kvarh, e_l2_reactive_ind_kvarh, e_l3_reactive_ind_kvarh, e_l1_reactive_cap_kvarh, e_l2_reactive_cap_kvarh, e_l3_reactive_cap_kvarh
FROM sog5_energy_logs 
ORDER BY log_date DESC 
LIMIT 10