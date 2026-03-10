-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 28/02/2026 às 12:52
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `crud-gestao-frotas`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `abastecimentos`
--

CREATE TABLE `abastecimentos` (
  `id` int(10) UNSIGNED NOT NULL,
  `viatura_id` int(10) UNSIGNED NOT NULL,
  `colaborador_id` int(10) UNSIGNED DEFAULT NULL,
  `posto` varchar(120) NOT NULL,
  `combustivel` varchar(30) NOT NULL,
  `litros` decimal(10,2) NOT NULL,
  `preco_litro` decimal(10,3) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `data_abastecimento` date NOT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `abastecimentos`
--

INSERT INTO `abastecimentos` (`id`, `viatura_id`, `colaborador_id`, `posto`, `combustivel`, `litros`, `preco_litro`, `total`, `data_abastecimento`, `observacoes`, `criado_em`, `latitude`, `longitude`) VALUES
(7, 29, NULL, 'Posto - Forum Algarve', 'Gasolina', 20.00, 1.970, 39.40, '2026-02-19', NULL, '2026-02-19 13:59:13', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `atribuicoes`
--

CREATE TABLE `atribuicoes` (
  `id` int(10) UNSIGNED NOT NULL,
  `viatura_id` int(10) UNSIGNED NOT NULL,
  `colaborador_id` int(10) UNSIGNED NOT NULL,
  `data_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `data_fim` datetime DEFAULT NULL,
  `notas` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `colaboradores`
--

CREATE TABLE `colaboradores` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `cargo` varchar(80) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `manutencoes`
--

CREATE TABLE `manutencoes` (
  `id` int(10) UNSIGNED NOT NULL,
  `viatura_id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('Preventiva','Corretiva','Inspeção','Pneus','Óleo','Outro') NOT NULL DEFAULT 'Outro',
  `descricao` varchar(255) NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `custo` decimal(10,2) DEFAULT NULL,
  `oficina` varchar(120) DEFAULT NULL,
  `status` enum('Aberta','Concluída','Cancelada') NOT NULL DEFAULT 'Aberta',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `manutencoes`
--

INSERT INTO `manutencoes` (`id`, `viatura_id`, `tipo`, `descricao`, `data_inicio`, `data_fim`, `custo`, `oficina`, `status`, `criado_em`) VALUES
(7, 25, 'Corretiva', 'Reparo no sistema de freios', '2024-02-20', NULL, 2300.00, 'Oficina Central', '', '2026-02-12 05:49:34'),
(8, 26, 'Preventiva', 'Revisão programada (100.000km)', '2024-03-05', NULL, 1450.00, 'Oficina Norte', '', '2026-02-12 05:49:34'),
(9, 27, 'Corretiva', 'Troca de embraiagem', '2024-03-12', NULL, 970.00, 'Oficina Central', '', '2026-02-12 05:49:34'),
(10, 28, 'Preventiva', 'Suspensão e alinhamento', '2024-03-18', NULL, 620.00, 'Oficina Sul', '', '2026-02-12 05:49:34'),
(12, 29, 'Corretiva', 'Problemas no motor', '2026-02-19', NULL, 1000.00, NULL, '', '2026-02-19 13:42:39');

-- --------------------------------------------------------

--
-- Estrutura para tabela `motoristas`
--

CREATE TABLE `motoristas` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `cc` varchar(20) DEFAULT NULL,
  `nif` varchar(20) DEFAULT NULL,
  `carta_numero` varchar(30) DEFAULT NULL,
  `carta_categoria` varchar(10) DEFAULT NULL,
  `carta_validade` date DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `status` enum('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
  `desde` date DEFAULT NULL,
  `viagens` int(11) NOT NULL DEFAULT 0,
  `viatura_id` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `motoristas`
--

INSERT INTO `motoristas` (`id`, `nome`, `cc`, `nif`, `carta_numero`, `carta_categoria`, `carta_validade`, `telefone`, `email`, `status`, `desde`, `viagens`, `viatura_id`, `criado_em`) VALUES
(9, 'Carlos Silva', '12345678 9 ZZ1', '123456789', 'PT-CC-001', 'C', '2026-08-15', '(+351) 912 345 678', 'carlos.silva@aquafleet.com', 'Ativo', '2020-03-10', 847, 17, '2026-02-12 05:48:53'),
(10, 'Ana Rodrigues', '23456789 0 ZZ2', '234567890', 'PT-CC-002', 'B', '2026-11-20', '(+351) 913 222 111', 'ana.rodrigues@aquafleet.com', 'Ativo', '2021-06-15', 523, 18, '2026-02-12 05:48:53'),
(11, 'Maria Santos', '45678901 2 ZZ4', '456789012', 'PT-CC-004', 'B', '2027-02-28', '(+351) 915 777 666', 'maria.santos@aquafleet.com', 'Ativo', '2022-09-01', 312, 19, '2026-02-12 05:48:53'),
(12, 'João Costa', '56789012 3 ZZ5', '567890123', 'PT-CC-005', 'C', '2026-07-05', '(+351) 916 888 222', 'joao.costa@aquafleet.com', 'Ativo', '2018-11-12', 1456, 20, '2026-02-12 05:48:53'),
(13, 'Beatriz Ferreira', '78901234 5 ZZ7', '789012345', 'PT-CC-007', 'B', '2026-10-02', '(+351) 918 222 333', 'beatriz.ferreira@aquafleet.com', 'Ativo', '2023-02-10', 124, 21, '2026-02-12 05:48:53'),
(14, 'Ricardo Lima', '67890123 4 ZZ6', '678901234', 'PT-CC-006', 'C', '2025-12-18', '(+351) 917 111 999', 'ricardo.lima@aquafleet.com', 'Ativo', '2020-07-22', 934, 22, '2026-02-12 05:48:53'),
(15, 'Rui Martins', '89012345 6 ZZ8', '890123456', 'PT-CC-008', 'B', '2025-09-12', '(+351) 919 999 888', 'rui.martins@aquafleet.com', 'Ativo', '2017-05-03', 778, 23, '2026-02-12 05:48:53'),
(16, 'Sofia Pereira', '90123456 7 ZZ9', '901234567', 'PT-CC-009', 'B', '2026-04-19', '(+351) 911 222 444', 'sofia.pereira@aquafleet.com', 'Ativo', '2021-01-14', 402, 24, '2026-02-12 05:48:53'),
(17, 'Pedro Almeida', '34567890 1 ZZ3', '345678901', 'PT-CC-003', 'C+E', '2025-05-10', '(+351) 914 555 444', 'pedro.almeida@aquafleet.com', 'Ativo', '2019-01-20', 1102, 25, '2026-02-12 05:48:53'),
(18, 'Tiago Ramos', '11223344 8 ZZ10', '112233445', 'PT-CC-010', 'C', '2026-03-21', '(+351) 913 444 555', 'tiago.ramos@aquafleet.com', 'Ativo', '2020-10-02', 689, 26, '2026-02-12 05:48:53'),
(19, 'Inês Carvalho', '22334455 9 ZZ11', '223344556', 'PT-CC-011', 'B', '2027-01-07', '(+351) 914 111 222', 'ines.carvalho@aquafleet.com', 'Ativo', '2022-05-30', 256, 27, '2026-02-12 05:48:53'),
(20, 'Miguel Duarte', '33445566 0 ZZ12', '334455667', 'PT-CC-012', 'B', '2026-06-11', '(+351) 915 333 999', 'miguel.duarte@aquafleet.com', 'Ativo', '2021-09-18', 471, 28, '2026-02-12 05:48:53');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `username` varchar(60) NOT NULL,
  `senha` varchar(60) NOT NULL,
  `perfil` varchar(40) NOT NULL DEFAULT 'Gestor',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `username`, `senha`, `perfil`, `ativo`, `criado_em`) VALUES
(1, 'Giovanni Santos', 'admin', '1234', 'admin', 1, '2026-01-16 23:20:52'),
(2, 'Alberto Cunha', 'gestor', '1234', 'gestor', 1, '2026-02-19 13:35:36'),
(3, 'Inês Castro', 'utilizador', '1234', 'operario', 1, '2026-02-19 13:36:13');

-- --------------------------------------------------------

--
-- Estrutura para tabela `viaturas`
--

CREATE TABLE `viaturas` (
  `id` int(10) UNSIGNED NOT NULL,
  `matricula` varchar(20) NOT NULL,
  `marca_modelo` varchar(120) NOT NULL,
  `tipo` enum('Ligeiro','Pick-up','Carrinha','Camião','Elétrico','Outro') NOT NULL DEFAULT 'Ligeiro',
  `combustivel` enum('Diesel','Gasolina','Elétrico','Híbrido','Outro') NOT NULL DEFAULT 'Diesel',
  `quilometragem` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `estado` enum('Disponível','Atribuída','Em Manutenção') NOT NULL DEFAULT 'Disponível',
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `viaturas`
--

INSERT INTO `viaturas` (`id`, `matricula`, `marca_modelo`, `tipo`, `combustivel`, `quilometragem`, `estado`, `observacoes`, `criado_em`, `atualizado_em`) VALUES
(17, 'ABC-1234', 'Mercedes-Benz Sprinter 515', 'Carrinha', 'Diesel', 45230, 'Atribuída', 'Rota operacional - ETAR Zona Norte', '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(18, 'DEF-5678', 'Toyota Hilux 2.8', 'Pick-up', 'Diesel', 23100, 'Atribuída', 'Rota: Reservatório Central → Bairro Leste', '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(19, 'JKL-3456', 'Fiat Strada Freedom', 'Pick-up', 'Gasolina', 12050, 'Atribuída', 'Apoio urbano e campo', '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(20, 'MNO-7890', 'Mercedes-Benz Accelo 815', 'Camião', 'Diesel', 110200, 'Atribuída', 'Transporte interno e apoio logístico', '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(21, 'STU-4567', 'Volkswagen Delivery 11.180', 'Camião', 'Diesel', 97200, 'Atribuída', 'Distribuição e apoio técnico', '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(22, 'KLM-5555', 'Renault Kangoo', 'Carrinha', 'Diesel', 68400, 'Atribuída', 'Serviços técnicos - zona oeste', '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(23, 'NOP-6666', 'Peugeot Partner', 'Carrinha', 'Diesel', 54420, 'Atribuída', 'Equipa de manutenção de rede', '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(24, 'RST-7777', 'Iveco Daily 35S', 'Carrinha', 'Diesel', 80310, 'Atribuída', 'Apoio operacional - zona sul', '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(25, 'GHI-9012', 'Ford Cargo 1119', 'Camião', 'Diesel', 89500, 'Em Manutenção', 'Reparo no sistema de freios', '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(26, 'QWE-2222', 'MAN TGL 8.180', 'Camião', 'Diesel', 121500, 'Em Manutenção', 'Revisão programada (100.000km)', '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(27, 'ASD-3333', 'Citroën Berlingo', 'Carrinha', 'Diesel', 73800, 'Em Manutenção', 'Troca de embraiagem', '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(28, 'HJK-4444', 'Isuzu D-Max 3.0', 'Pick-up', 'Diesel', 65800, 'Em Manutenção', 'Suspensão e alinhamento', '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(29, 'TUV-1111', 'Dacia Duster', 'Ligeiro', 'Gasolina', 28900, 'Disponível', 'Disponível para atribuição', '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(30, 'VWX-7788', 'Nissan Leaf', 'Ligeiro', 'Elétrico', 20500, 'Disponível', 'Viatura elétrica para deslocações curtas', '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(32, 'ZZZ-0002', 'Opel Combo', 'Carrinha', 'Diesel', 143200, '', 'Viatura desativada / sucata', '2026-02-12 05:48:09', '2026-02-12 05:48:09');

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_dashboard_viaturas`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_dashboard_viaturas` (
`total` bigint(21)
,`disponiveis` bigint(21)
,`em_manutencao` bigint(21)
,`atribuidas` bigint(21)
);

-- --------------------------------------------------------

--
-- Estrutura para view `vw_dashboard_viaturas`
--
DROP TABLE IF EXISTS `vw_dashboard_viaturas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_dashboard_viaturas`  AS SELECT (select count(0) from `viaturas`) AS `total`, (select count(0) from `viaturas` where `viaturas`.`estado` = 'Disponível') AS `disponiveis`, (select count(0) from `viaturas` where `viaturas`.`estado` = 'Em Manutenção') AS `em_manutencao`, (select count(0) from `viaturas` where `viaturas`.`estado` = 'Atribuída') AS `atribuidas` ;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `abastecimentos`
--
ALTER TABLE `abastecimentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_abast_viatura` (`viatura_id`),
  ADD KEY `idx_abast_colab` (`colaborador_id`);

--
-- Índices de tabela `atribuicoes`
--
ALTER TABLE `atribuicoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_atr_viatura` (`viatura_id`),
  ADD KEY `idx_atr_colaborador` (`colaborador_id`),
  ADD KEY `idx_atr_abertas` (`viatura_id`,`data_fim`);

--
-- Índices de tabela `colaboradores`
--
ALTER TABLE `colaboradores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `manutencoes`
--
ALTER TABLE `manutencoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_man_viatura` (`viatura_id`),
  ADD KEY `idx_man_status` (`status`);

--
-- Índices de tabela `motoristas`
--
ALTER TABLE `motoristas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_motoristas_nif` (`nif`),
  ADD KEY `viatura_id` (`viatura_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Índices de tabela `viaturas`
--
ALTER TABLE `viaturas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricula` (`matricula`),
  ADD KEY `idx_viaturas_estado` (`estado`),
  ADD KEY `idx_viaturas_tipo` (`tipo`),
  ADD KEY `idx_viaturas_combustivel` (`combustivel`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `abastecimentos`
--
ALTER TABLE `abastecimentos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `atribuicoes`
--
ALTER TABLE `atribuicoes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `colaboradores`
--
ALTER TABLE `colaboradores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `manutencoes`
--
ALTER TABLE `manutencoes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `motoristas`
--
ALTER TABLE `motoristas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `viaturas`
--
ALTER TABLE `viaturas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `abastecimentos`
--
ALTER TABLE `abastecimentos`
  ADD CONSTRAINT `fk_abast_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_abast_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `atribuicoes`
--
ALTER TABLE `atribuicoes`
  ADD CONSTRAINT `fk_atr_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_atr_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `manutencoes`
--
ALTER TABLE `manutencoes`
  ADD CONSTRAINT `fk_man_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `motoristas`
--
ALTER TABLE `motoristas`
  ADD CONSTRAINT `fk_motoristas_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
