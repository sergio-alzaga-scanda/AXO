-- ==========================================================
-- SCRIPT CORRECTO DE INSERCIÓN DE HORARIOS (CON IDs EXACTOS)
-- ==========================================================

-- 1. Pedro Daniel Mendez Mora (ID: 9)
-- Lun-Dom 09:00-18:00 | Comida: 15:00-16:00
INSERT INTO horarios_tecnicos (id_tecnico, dia_semana, hora_entrada, hora_salida, inicio_comida, fin_comida) VALUES 
(9, 'Mon', '09:00:00', '18:00:00', '15:00:00', '16:00:00'),
(9, 'Tue', '09:00:00', '18:00:00', '15:00:00', '16:00:00'),
(9, 'Wed', '09:00:00', '18:00:00', '15:00:00', '16:00:00'),
(9, 'Thu', '09:00:00', '18:00:00', '15:00:00', '16:00:00'),
(9, 'Fri', '09:00:00', '18:00:00', '15:00:00', '16:00:00'),
(9, 'Sat', '09:00:00', '18:00:00', '15:00:00', '16:00:00'),
(9, 'Sun', '09:00:00', '18:00:00', '15:00:00', '16:00:00');

-- 2. Raul Cortez Vargas (ID: 10)
-- Lun-Vie 06:00-15:00 | Comida 12:00-13:00
INSERT INTO horarios_tecnicos (id_tecnico, dia_semana, hora_entrada, hora_salida, inicio_comida, fin_comida) VALUES 
(10, 'Mon', '06:00:00', '15:00:00', '12:00:00', '13:00:00'),
(10, 'Tue', '06:00:00', '15:00:00', '12:00:00', '13:00:00'),
(10, 'Wed', '06:00:00', '15:00:00', '12:00:00', '13:00:00'),
(10, 'Thu', '06:00:00', '15:00:00', '12:00:00', '13:00:00'),
(10, 'Fri', '06:00:00', '15:00:00', '12:00:00', '13:00:00'),
(10, 'Sat', '06:00:00', '15:00:00', '12:00:00', '13:00:00'),
(10, 'Sun', '08:00:00', '15:00:00', '12:00:00', '13:00:00');

-- 3. Luis Angel Sandoval Sevilla (ID: 6)
-- Lun-Vie 14:00-23:00 | Comida 19:00-20:00
INSERT INTO horarios_tecnicos (id_tecnico, dia_semana, hora_entrada, hora_salida, inicio_comida, fin_comida) VALUES 
(6, 'Mon', '14:00:00', '23:00:00', '19:00:00', '20:00:00'),
(6, 'Tue', '14:00:00', '23:00:00', '19:00:00', '20:00:00'),
(6, 'Wed', '14:00:00', '23:00:00', '19:00:00', '20:00:00'),
(6, 'Thu', '14:00:00', '23:00:00', '19:00:00', '20:00:00'),
(6, 'Fri', '14:00:00', '23:00:00', '19:00:00', '20:00:00');

-- 4. Iker Michel Castañeda Sanchez (ID: 3)
-- Lun-Dom 11:00-20:00 | Comida 17:00-18:00
INSERT INTO horarios_tecnicos (id_tecnico, dia_semana, hora_entrada, hora_salida, inicio_comida, fin_comida) VALUES 
(3, 'Mon', '11:00:00', '20:00:00', '17:00:00', '18:00:00'),
(3, 'Tue', '11:00:00', '20:00:00', '17:00:00', '18:00:00'),
(3, 'Wed', '11:00:00', '20:00:00', '17:00:00', '18:00:00'),
(3, 'Thu', '11:00:00', '20:00:00', '17:00:00', '18:00:00'),
(3, 'Fri', '11:00:00', '20:00:00', '17:00:00', '18:00:00'),
(3, 'Sat', '11:00:00', '20:00:00', '17:00:00', '18:00:00'),
(3, 'Sun', '11:00:00', '20:00:00', '17:00:00', '18:00:00');

-- 5. Liliam Cruz Galindo (ID: 7)
-- Lun-Vie 07:00-16:00 | Comida 13:00-14:00
INSERT INTO horarios_tecnicos (id_tecnico, dia_semana, hora_entrada, hora_salida, inicio_comida, fin_comida) VALUES 
(7, 'Mon', '07:00:00', '16:00:00', '13:00:00', '14:00:00'),
(7, 'Tue', '07:00:00', '16:00:00', '13:00:00', '14:00:00'),
(7, 'Wed', '07:00:00', '16:00:00', '13:00:00', '14:00:00'),
(7, 'Thu', '07:00:00', '16:00:00', '13:00:00', '14:00:00'),
(7, 'Fri', '07:00:00', '16:00:00', '13:00:00', '14:00:00');

-- 6. Russbelly Silva Nicolás (ID: 11)
-- Lun-Vie 12:00-21:00 | Comida 18:00-19:00
INSERT INTO horarios_tecnicos (id_tecnico, dia_semana, hora_entrada, hora_salida, inicio_comida, fin_comida) VALUES 
(11, 'Mon', '12:00:00', '21:00:00', '18:00:00', '19:00:00'),
(11, 'Tue', '12:00:00', '21:00:00', '18:00:00', '19:00:00'),
(11, 'Wed', '12:00:00', '21:00:00', '18:00:00', '19:00:00'),
(11, 'Thu', '12:00:00', '21:00:00', '18:00:00', '19:00:00'),
(11, 'Fri', '12:00:00', '21:00:00', '18:00:00', '19:00:00');

-- 7. Juan Carlos López Moreno (ID: 5)
-- Lun-Vie 10:00-19:00 | Comida 16:00-17:00
INSERT INTO horarios_tecnicos (id_tecnico, dia_semana, hora_entrada, hora_salida, inicio_comida, fin_comida) VALUES 
(5, 'Mon', '10:00:00', '19:00:00', '16:00:00', '17:00:00'),
(5, 'Tue', '10:00:00', '19:00:00', '16:00:00', '17:00:00'),
(5, 'Wed', '10:00:00', '19:00:00', '16:00:00', '17:00:00'),
(5, 'Thu', '10:00:00', '19:00:00', '16:00:00', '17:00:00'),
(5, 'Fri', '10:00:00', '19:00:00', '16:00:00', '17:00:00');

-- 8. Luis Alberto Chavez Orocio (ID: 16)
-- Lun-Vie 08:00-17:00 | Comida 14:00-15:00
INSERT INTO horarios_tecnicos (id_tecnico, dia_semana, hora_entrada, hora_salida, inicio_comida, fin_comida) VALUES 
(16, 'Mon', '08:00:00', '17:00:00', '14:00:00', '15:00:00'),
(16, 'Tue', '08:00:00', '17:00:00', '14:00:00', '15:00:00'),
(16, 'Wed', '08:00:00', '17:00:00', '14:00:00', '15:00:00'),
(16, 'Thu', '08:00:00', '17:00:00', '14:00:00', '15:00:00'),
(16, 'Fri', '08:00:00', '17:00:00', '14:00:00', '15:00:00');

-- 9. Salvado Valenzuela Vazquez (ID: 12)
-- Miercoles y Viernes 09:00-18:00 | Comida 14:00-15:00
INSERT INTO horarios_tecnicos (id_tecnico, dia_semana, hora_entrada, hora_salida, inicio_comida, fin_comida) VALUES 
(12, 'Wed', '09:00:00', '18:00:00', '14:00:00', '15:00:00'),
(12, 'Fri', '09:00:00', '18:00:00', '14:00:00', '15:00:00');

-- 10. Mario Montiel Zapien (ID: 21)
-- Lun-Vie 13:00-22:00 | Comida 18:00-19:00 | Sab-Dom 14:00-23:00
INSERT INTO horarios_tecnicos (id_tecnico, dia_semana, hora_entrada, hora_salida, inicio_comida, fin_comida) VALUES 
(21, 'Mon', '13:00:00', '22:00:00', '18:00:00', '19:00:00'),
(21, 'Tue', '13:00:00', '22:00:00', '18:00:00', '19:00:00'),
(21, 'Wed', '13:00:00', '22:00:00', '18:00:00', '19:00:00'),
(21, 'Thu', '13:00:00', '22:00:00', '18:00:00', '19:00:00'),
(21, 'Fri', '13:00:00', '22:00:00', '18:00:00', '19:00:00'),
(21, 'Sat', '14:00:00', '23:00:00', '18:00:00', '19:00:00'),
(21, 'Sun', '14:00:00', '23:00:00', '18:00:00', '19:00:00');
