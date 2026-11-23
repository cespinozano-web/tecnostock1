DROP DATABASE IF EXISTS tecnostock1;

CREATE DATABASE tecnostock1
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE tecnostock1;

CREATE TABLE categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(100) NOT NULL,
    descripcion_categoria VARCHAR(800) NULL,
    estado_categoria TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE cargo (
    id_cargo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_cargo VARCHAR(100) NOT NULL,
    descripcion_cargo VARCHAR(800) NULL,
    estado_cargo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE producto (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    codigo_producto INT NOT NULL UNIQUE,
    nombre_producto VARCHAR(300) NOT NULL,
    descripcion_producto VARCHAR(800) NULL,
    precio_producto INT NOT NULL,
    stock_actual_producto INT NOT NULL DEFAULT 0,
    stock_minimo_producto INT NOT NULL DEFAULT 0,
    estado_producto TINYINT(1) NOT NULL DEFAULT 1,
    id_categoria INT NOT NULL,
    CONSTRAINT fk_producto_categoria
        FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(80) NOT NULL,
    correo_usuario VARCHAR(120) NOT NULL UNIQUE,
    clave_usuario VARCHAR(255) NOT NULL,
    estado_usuario TINYINT(1) NOT NULL DEFAULT 1,
    telefono_usuario VARCHAR(20) NULL,
    id_cargo INT NOT NULL,
    CONSTRAINT fk_usuario_cargo
        FOREIGN KEY (id_cargo) REFERENCES cargo(id_cargo)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE movimiento (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    fecha_hora_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo_movimiento VARCHAR(100) NOT NULL,
    cantidad_movimiento INT NOT NULL,
    unidades_movimiento INT NOT NULL DEFAULT 0,
    observacion_movimiento VARCHAR(800) NULL,
    estado_movimiento TINYINT(1) NOT NULL DEFAULT 1,
    id_producto INT NOT NULL,
    id_usuario INT NOT NULL,
    CONSTRAINT fk_movimiento_producto
        FOREIGN KEY (id_producto) REFERENCES producto(id_producto)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_movimiento_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO cargo (nombre_cargo, descripcion_cargo, estado_cargo) VALUES
('Dueño', 'Acceso total al sistema', 1),
('Administrador', 'Gestión de usuarios, stock y alertas', 1),
('Usuario', 'Operación de bodega y órdenes', 1);

INSERT INTO categoria (nombre_categoria, descripcion_categoria, estado_categoria) VALUES
('General', 'Categoría inicial de prueba', 1);

INSERT INTO usuario (nombre_usuario, correo_usuario, clave_usuario, estado_usuario, telefono_usuario, id_cargo) VALUES
('dueño', 'dueño@gmail.com', 'dueño1', 1, NULL, 1),
('admin', 'admin@gmail.com', 'admin1', 1, NULL, 2),
('user', 'user@gmail.com', 'user1', 1, NULL, 3);