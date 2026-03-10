CREATE TABLE IF NOT EXISTS motoristas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  nome VARCHAR(120) NOT NULL,
  cc VARCHAR(20) NULL,
  nif VARCHAR(20) NULL,

  carta_numero VARCHAR(30) NULL,
  carta_categoria VARCHAR(10) NULL,
  carta_validade DATE NULL,

  telefone VARCHAR(30) NULL,
  email VARCHAR(120) NULL,

  status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  desde DATE NULL,
  viagens INT NOT NULL DEFAULT 0,

  viatura_id INT UNSIGNED NULL,

  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX (viatura_id),
  UNIQUE KEY uq_motoristas_nif (nif),

  CONSTRAINT fk_motoristas_viatura
    FOREIGN KEY (viatura_id) REFERENCES viaturas(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
