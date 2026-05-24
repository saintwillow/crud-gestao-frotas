-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 23/05/2026 às 01:17
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
  `motorista_id` int(10) UNSIGNED DEFAULT NULL,
  `registado_por_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `servico_id` int(10) UNSIGNED DEFAULT NULL,
  `posto` varchar(120) DEFAULT NULL,
  `combustivel` varchar(30) NOT NULL,
  `litros` decimal(10,2) NOT NULL,
  `preco_litro` decimal(10,3) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `km_atual` int(10) UNSIGNED DEFAULT NULL,
  `data_abastecimento` date NOT NULL,
  `observacoes` text DEFAULT NULL,
  `comprovativo` varchar(255) DEFAULT NULL,
  `estado` enum('registado','em_analise','corrigido','anulado') NOT NULL DEFAULT 'registado',
  `aprovado_por_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL,
  `motivo_rejeicao` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `abastecimentos`
--

INSERT INTO `abastecimentos` (`id`, `viatura_id`, `colaborador_id`, `motorista_id`, `registado_por_usuario_id`, `servico_id`, `posto`, `combustivel`, `litros`, `preco_litro`, `total`, `km_atual`, `data_abastecimento`, `observacoes`, `comprovativo`, `estado`, `aprovado_por_usuario_id`, `aprovado_em`, `motivo_rejeicao`, `criado_em`, `latitude`, `longitude`) VALUES
(7, 29, NULL, NULL, NULL, NULL, 'Posto - Forum Algarve', 'Gasolina', 20.00, 1.970, 39.40, NULL, '2026-02-19', NULL, NULL, 'registado', NULL, NULL, NULL, '2026-02-19 13:59:13', NULL, NULL),
(8, 29, NULL, NULL, NULL, NULL, 'Faro', 'Gasolina', 40.00, 2.560, 102.40, NULL, '2026-03-17', NULL, NULL, 'registado', NULL, NULL, NULL, '2026-03-17 10:57:43', 37.0161141, -7.9275537),
(9, 28, NULL, NULL, NULL, NULL, 'Montenegro', 'Etanol', 50.00, 3.090, 154.50, NULL, '2026-03-17', NULL, NULL, 'registado', NULL, NULL, NULL, '2026-03-17 11:00:17', 37.0244573, -7.9664134),
(10, 20, NULL, NULL, NULL, NULL, 'Olhao', 'Diesel', 63.00, 2.870, 180.81, NULL, '2026-03-17', NULL, NULL, 'registado', NULL, NULL, NULL, '2026-03-17 11:01:46', 37.0311044, -7.8421737),
(11, 25, NULL, NULL, NULL, NULL, 'Armação de Pera', 'Gasolina', 40.00, 1.780, 71.20, NULL, '2026-03-17', NULL, NULL, 'em_analise', 2, '2026-05-22 12:24:05', NULL, '2026-03-17 12:29:39', 37.1052060, -8.3657010),
(13, 22, 11, 19, 3, 2, 'Montenegro', 'Diesel', 12.00, 2.670, 32.04, 68400, '2026-05-21', NULL, NULL, 'registado', NULL, NULL, NULL, '2026-05-21 21:25:44', 37.3001610, -8.6454610),
(14, 22, 11, 19, 3, 3, 'Galp Teste', 'Diesel', 20.00, 1.750, 35.00, 68784, '2026-05-21', NULL, NULL, 'registado', NULL, NULL, NULL, '2026-05-21 21:32:38', NULL, NULL),
(15, 22, 11, 19, 3, 3, NULL, 'Diesel', 15.00, 1.998, 29.97, NULL, '2026-05-21', NULL, NULL, 'anulado', 2, '2026-05-22 12:23:54', NULL, '2026-05-21 21:49:05', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `atribuicoes`
--

CREATE TABLE `atribuicoes` (
  `id` int(10) UNSIGNED NOT NULL,
  `viatura_id` int(10) UNSIGNED NOT NULL,
  `colaborador_id` int(10) UNSIGNED NOT NULL,
  `motorista_id` int(10) UNSIGNED DEFAULT NULL,
  `data_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `km_inicio` int(10) UNSIGNED DEFAULT NULL,
  `data_fim` datetime DEFAULT NULL,
  `km_fim` int(10) UNSIGNED DEFAULT NULL,
  `estado` enum('aberta','encerrada','cancelada') NOT NULL DEFAULT 'aberta',
  `notas` varchar(255) DEFAULT NULL,
  `criado_por_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `encerrado_por_usuario_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `atribuicoes`
--

INSERT INTO `atribuicoes` (`id`, `viatura_id`, `colaborador_id`, `motorista_id`, `data_inicio`, `km_inicio`, `data_fim`, `km_fim`, `estado`, `notas`, `criado_por_usuario_id`, `encerrado_por_usuario_id`) VALUES
(1, 17, 1, 9, '2026-05-21 16:19:56', 45230, NULL, NULL, 'aberta', 'Atribuição criada automaticamente pela migração', NULL, NULL),
(2, 18, 2, 10, '2026-05-21 16:19:56', 23100, NULL, NULL, 'aberta', 'Atribuição criada automaticamente pela migração', NULL, NULL),
(3, 19, 3, 11, '2026-05-21 16:19:56', 12050, NULL, NULL, 'aberta', 'Atribuição criada automaticamente pela migração', NULL, NULL),
(4, 20, 4, 12, '2026-05-21 16:19:56', 110200, NULL, NULL, 'aberta', 'Atribuição criada automaticamente pela migração', NULL, NULL),
(5, 21, 5, 13, '2026-05-21 16:19:56', 97200, NULL, NULL, 'aberta', 'Atribuição criada automaticamente pela migração', NULL, NULL),
(6, 22, 6, 14, '2026-05-21 16:19:56', 68400, '2026-05-21 16:35:55', NULL, 'encerrada', 'Atribuição criada automaticamente pela migração | Encerrada para corrigir duplicidade de viatura na migração.', NULL, NULL),
(7, 23, 7, 15, '2026-05-21 16:19:56', 54420, NULL, NULL, 'aberta', 'Atribuição criada automaticamente pela migração', NULL, NULL),
(8, 24, 8, 16, '2026-05-21 16:19:56', 80310, NULL, NULL, 'aberta', 'Atribuição criada automaticamente pela migração', NULL, NULL),
(9, 25, 9, 17, '2026-05-21 16:19:56', 89500, NULL, NULL, 'aberta', 'Atribuição criada automaticamente pela migração', NULL, NULL),
(10, 26, 10, 18, '2026-05-21 16:19:56', 121500, NULL, NULL, 'aberta', 'Atribuição criada automaticamente pela migração', NULL, NULL),
(11, 22, 11, 19, '2026-05-21 16:19:56', 68400, NULL, 68400, 'aberta', 'Atribuição criada automaticamente pela migração', NULL, NULL),
(12, 28, 12, 20, '2026-05-21 16:19:56', 65800, NULL, NULL, 'aberta', 'Atribuição criada automaticamente pela migração', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `checklists_servico`
--

CREATE TABLE `checklists_servico` (
  `id` int(10) UNSIGNED NOT NULL,
  `servico_id` int(10) UNSIGNED NOT NULL,
  `momento` enum('inicio','fim') NOT NULL,
  `pneus_ok` tinyint(1) DEFAULT NULL,
  `luzes_ok` tinyint(1) DEFAULT NULL,
  `travoes_ok` tinyint(1) DEFAULT NULL,
  `documentos_ok` tinyint(1) DEFAULT NULL,
  `limpeza_ok` tinyint(1) DEFAULT NULL,
  `danos_visiveis` tinyint(1) DEFAULT NULL,
  `nivel_combustivel` tinyint(3) UNSIGNED DEFAULT NULL,
  `quilometragem` int(10) UNSIGNED DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `checklists_servico`
--

INSERT INTO `checklists_servico` (`id`, `servico_id`, `momento`, `pneus_ok`, `luzes_ok`, `travoes_ok`, `documentos_ok`, `limpeza_ok`, `danos_visiveis`, `nivel_combustivel`, `quilometragem`, `observacoes`, `foto`, `criado_em`) VALUES
(1, 1, 'inicio', 1, 1, 1, 1, 1, 0, NULL, 68400, NULL, NULL, '2026-05-21 16:18:46'),
(2, 1, 'fim', 1, 1, 1, 1, 1, 0, NULL, 68400, NULL, NULL, '2026-05-21 16:25:07'),
(3, 2, 'inicio', 1, 1, 1, 1, 1, 1, 80, 68400, NULL, NULL, '2026-05-21 16:26:37'),
(4, 2, 'fim', 1, 1, 1, 1, 1, 0, NULL, 68400, NULL, NULL, '2026-05-21 21:30:52'),
(5, 3, 'inicio', 1, 1, 1, 1, 1, 0, NULL, 68400, NULL, NULL, '2026-05-21 21:31:34');

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

--
-- Despejando dados para a tabela `colaboradores`
--

INSERT INTO `colaboradores` (`id`, `nome`, `email`, `telefone`, `cargo`, `ativo`, `criado_em`) VALUES
(1, 'Carlos Silva', 'carlos.silva@aquafleet.com', '(+351) 912 345 678', 'Motorista', 1, '2026-02-12 05:48:53'),
(2, 'Ana Rodrigues', 'ana.rodrigues@aquafleet.com', '(+351) 913 222 111', 'Motorista', 1, '2026-02-12 05:48:53'),
(3, 'Maria Santos', 'maria.santos@aquafleet.com', '(+351) 915 777 666', 'Motorista', 1, '2026-02-12 05:48:53'),
(4, 'João Costa', 'joao.costa@aquafleet.com', '(+351) 916 888 222', 'Motorista', 1, '2026-02-12 05:48:53'),
(5, 'Beatriz Ferreira', 'beatriz.ferreira@aquafleet.com', '(+351) 918 222 333', 'Motorista', 1, '2026-02-12 05:48:53'),
(6, 'Ricardo Lima', 'ricardo.lima@aquafleet.com', '(+351) 917 111 999', 'Motorista', 1, '2026-02-12 05:48:53'),
(7, 'Rui Martins', 'rui.martins@aquafleet.com', '(+351) 919 999 888', 'Motorista', 1, '2026-02-12 05:48:53'),
(8, 'Sofia Pereira', 'sofia.pereira@aquafleet.com', '(+351) 911 222 444', 'Motorista', 1, '2026-02-12 05:48:53'),
(9, 'Pedro Almeida', 'pedro.almeida@aquafleet.com', '(+351) 914 555 444', 'Motorista', 1, '2026-02-12 05:48:53'),
(10, 'Tiago Ramos', 'tiago.ramos@aquafleet.com', '(+351) 913 444 555', 'Motorista', 1, '2026-02-12 05:48:53'),
(11, 'Inês Carvalho', 'ines.carvalho@aquafleet.com', '(+351) 914 111 222', 'Motorista', 1, '2026-02-12 05:48:53'),
(12, 'Miguel Duarte', 'miguel.duarte@aquafleet.com', '(+351) 915 333 999', 'Motorista', 1, '2026-02-12 05:48:53');

-- --------------------------------------------------------

--
-- Estrutura para tabela `infraestruturas`
--

CREATE TABLE `infraestruturas` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `tipo` enum('ETA','ETAR') NOT NULL,
  `concelho` varchar(100) DEFAULT NULL,
  `localidade` varchar(150) DEFAULT NULL,
  `sub_regiao` varchar(100) NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `infraestruturas`
--

INSERT INTO `infraestruturas` (`id`, `nome`, `tipo`, `concelho`, `localidade`, `sub_regiao`, `latitude`, `longitude`, `ativo`, `criado_em`) VALUES
(1, 'ETA de Alcantarilha', 'ETA', 'Silves', 'Alcantarilha', 'Albufeira / Silves', 37.1306000, -8.3465000, 1, '2026-03-15 17:43:58'),
(2, 'ETA de Fontaínhas', 'ETA', 'Portimão', 'Mexilhoeira Grande', 'Lagos / Portimão', 37.1904000, -8.6248000, 1, '2026-03-15 17:43:58'),
(3, 'ETA de Tavira', 'ETA', 'Tavira', 'Tavira', 'Tavira / Olhão / VRSA', 37.1250000, -7.6480000, 1, '2026-03-15 17:43:58'),
(4, 'ETA de Beliche', 'ETA', 'Castro Marim', 'Beliche', 'Tavira / Olhão / VRSA', 37.2508000, -7.5037000, 1, '2026-03-15 17:43:58'),
(5, 'ETAR de Lagos', 'ETAR', 'Lagos', 'São Sebastião', 'Lagos / Portimão', 37.1156000, -8.6745000, 1, '2026-03-15 17:43:58'),
(6, 'ETAR da Companheira', 'ETAR', 'Portimão', 'Portimão', 'Lagos / Portimão', 37.1465000, -8.5482000, 1, '2026-03-15 17:43:58'),
(7, 'ETAR de Albufeira Poente', 'ETAR', 'Albufeira', 'Guia', 'Albufeira / Silves', 37.1037000, -8.3013000, 1, '2026-03-15 17:43:58'),
(8, 'ETAR de Vale de Faro', 'ETAR', 'Albufeira', 'Vale de Faro', 'Albufeira / Silves', 37.0892000, -8.2462000, 1, '2026-03-15 17:43:58'),
(9, 'ETAR de Faro Noroeste', 'ETAR', 'Faro', 'Montenegro', 'Faro / Loulé / São Brás', 37.0340000, -7.9656000, 1, '2026-03-15 17:43:58'),
(10, 'ETAR de Faro/Olhão', 'ETAR', 'Faro', 'Sítio da Garganta', 'Faro / Loulé / São Brás', 37.0480000, -7.8248000, 1, '2026-03-15 17:43:58'),
(11, 'ETAR de Vilamoura', 'ETAR', 'Loulé', 'Quarteira', 'Faro / Loulé / São Brás', 37.0771000, -8.1009000, 1, '2026-03-15 17:43:58'),
(12, 'ETAR Quinta do Lago', 'ETAR', 'Loulé', 'Quinta do Lago', 'Faro / Loulé / São Brás', 37.0399000, -8.0295000, 1, '2026-03-15 17:43:58'),
(13, 'ETAR de Almargem', 'ETAR', 'Tavira', 'Cabanas de Tavira', 'Tavira / Olhão / VRSA', 37.1386000, -7.6016000, 1, '2026-03-15 17:43:58'),
(14, 'ETAR de Olhão Nascente', 'ETAR', 'Olhão', 'Quelfes', 'Tavira / Olhão / VRSA', 37.0377000, -7.8392000, 1, '2026-03-15 17:43:58'),
(15, 'ETAR de Vila Real de Santo António', 'ETAR', 'Vila Real de Santo António', 'Carrasqueira', 'Tavira / Olhão / VRSA', 37.1900000, -7.4173000, 1, '2026-03-15 17:43:58');

-- --------------------------------------------------------

--
-- Estrutura para tabela `manutencoes`
--

CREATE TABLE `manutencoes` (
  `id` int(10) UNSIGNED NOT NULL,
  `pedido_manutencao_id` int(10) UNSIGNED DEFAULT NULL,
  `viatura_id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('Preventiva','Corretiva','Inspeção','Pneus','Óleo','Outro') NOT NULL DEFAULT 'Outro',
  `descricao` varchar(255) NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `custo` decimal(10,2) DEFAULT NULL,
  `oficina` varchar(120) DEFAULT NULL,
  `status` enum('Agendada','Pendente','Em andamento','Concluída','Cancelada') NOT NULL DEFAULT 'Agendada',
  `criado_por_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `concluido_por_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `concluido_em` datetime DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `manutencoes`
--

INSERT INTO `manutencoes` (`id`, `pedido_manutencao_id`, `viatura_id`, `tipo`, `descricao`, `data_inicio`, `data_fim`, `custo`, `oficina`, `status`, `criado_por_usuario_id`, `concluido_por_usuario_id`, `concluido_em`, `observacoes`, `criado_em`) VALUES
(7, NULL, 25, 'Corretiva', 'Reparo no sistema de freios', '2024-02-20', NULL, 2300.00, 'Oficina Central', 'Pendente', NULL, NULL, NULL, NULL, '2026-02-12 05:49:34'),
(8, NULL, 26, 'Preventiva', 'Revisão programada (100.000km)', '2024-03-05', NULL, 1450.00, 'Oficina Norte', 'Pendente', NULL, NULL, NULL, NULL, '2026-02-12 05:49:34'),
(9, NULL, 27, 'Corretiva', 'Troca de embraiagem', '2024-03-12', NULL, 970.00, 'Oficina Central', 'Pendente', NULL, NULL, NULL, NULL, '2026-02-12 05:49:34'),
(10, NULL, 28, 'Preventiva', 'Suspensão e alinhamento', '2024-03-18', NULL, 620.00, 'Oficina Sul', 'Pendente', NULL, NULL, NULL, NULL, '2026-02-12 05:49:34'),
(12, NULL, 29, 'Corretiva', 'Problemas no motor', '2026-02-19', NULL, 1000.00, NULL, 'Pendente', NULL, NULL, NULL, NULL, '2026-02-19 13:42:39');

-- --------------------------------------------------------

--
-- Estrutura para tabela `motoristas`
--

CREATE TABLE `motoristas` (
  `id` int(10) UNSIGNED NOT NULL,
  `colaborador_id` int(10) UNSIGNED DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `motoristas`
--

INSERT INTO `motoristas` (`id`, `colaborador_id`, `nome`, `cc`, `nif`, `carta_numero`, `carta_categoria`, `carta_validade`, `telefone`, `email`, `status`, `desde`, `viagens`, `viatura_id`, `criado_em`) VALUES
(9, 1, 'Carlos Silva', '12345678 9 ZZ1', '123456789', 'PT-CC-001', 'C', '2026-08-15', '(+351) 912 345 678', 'carlos.silva@aquafleet.com', 'Ativo', '2020-03-10', 847, 17, '2026-02-12 05:48:53'),
(10, 2, 'Ana Rodrigues', '23456789 0 ZZ2', '234567890', 'PT-CC-002', 'B', '2026-11-20', '(+351) 913 222 111', 'ana.rodrigues@aquafleet.com', 'Ativo', '2021-06-15', 523, 18, '2026-02-12 05:48:53'),
(11, 3, 'Maria Santos', '45678901 2 ZZ4', '456789012', 'PT-CC-004', 'B', '2027-02-28', '(+351) 915 777 666', 'maria.santos@aquafleet.com', 'Ativo', '2022-09-01', 312, 19, '2026-02-12 05:48:53'),
(12, 4, 'João Costa', '56789012 3 ZZ5', '567890123', 'PT-CC-005', 'C', '2026-07-05', '(+351) 916 888 222', 'joao.costa@aquafleet.com', 'Ativo', '2018-11-12', 1456, 20, '2026-02-12 05:48:53'),
(13, 5, 'Beatriz Ferreira', '78901234 5 ZZ7', '789012345', 'PT-CC-007', 'B', '2026-10-02', '(+351) 918 222 333', 'beatriz.ferreira@aquafleet.com', 'Ativo', '2023-02-10', 124, 21, '2026-02-12 05:48:53'),
(14, 6, 'Ricardo Lima', '67890123 4 ZZ6', '678901234', 'PT-CC-006', 'C', '2025-12-18', '(+351) 917 111 999', 'ricardo.lima@aquafleet.com', 'Ativo', '2020-07-22', 934, 22, '2026-02-12 05:48:53'),
(15, 7, 'Rui Martins', '89012345 6 ZZ8', '890123456', 'PT-CC-008', 'B', '2025-09-12', '(+351) 919 999 888', 'rui.martins@aquafleet.com', 'Ativo', '2017-05-03', 778, 23, '2026-02-12 05:48:53'),
(16, 8, 'Sofia Pereira', '90123456 7 ZZ9', '901234567', 'PT-CC-009', 'B', '2026-04-19', '(+351) 911 222 444', 'sofia.pereira@aquafleet.com', 'Ativo', '2021-01-14', 402, 24, '2026-02-12 05:48:53'),
(17, 9, 'Pedro Almeida', '34567890 1 ZZ3', '345678901', 'PT-CC-003', 'C+E', '2025-05-10', '(+351) 914 555 444', 'pedro.almeida@aquafleet.com', 'Ativo', '2019-01-20', 1102, 25, '2026-02-12 05:48:53'),
(18, 10, 'Tiago Ramos', '11223344 8 ZZ10', '112233445', 'PT-CC-010', 'C', '2026-03-21', '(+351) 913 444 555', 'tiago.ramos@aquafleet.com', 'Ativo', '2020-10-02', 689, 26, '2026-02-12 05:48:53'),
(19, 11, 'Inês Carvalho', '22334455 9 ZZ11', '223344556', 'PT-CC-011', 'B', '2027-01-07', '(+351) 914 111 222', 'ines.carvalho@aquafleet.com', 'Ativo', '2022-05-30', 256, 22, '2026-02-12 05:48:53'),
(20, 12, 'Miguel Duarte', '33445566 0 ZZ12', '334455667', 'PT-CC-012', 'B', '2026-06-11', '(+351) 915 333 999', 'miguel.duarte@aquafleet.com', 'Ativo', '2021-09-18', 471, 28, '2026-02-12 05:48:53');

-- --------------------------------------------------------

--
-- Estrutura para tabela `ocorrencias`
--

CREATE TABLE `ocorrencias` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `servico_id` int(10) UNSIGNED DEFAULT NULL,
  `viatura_id` int(10) UNSIGNED NOT NULL,
  `motorista_id` int(10) UNSIGNED DEFAULT NULL,
  `criado_por_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `tipo` enum('avaria','acidente','dano','documentacao','seguranca','outro') NOT NULL DEFAULT 'outro',
  `gravidade` enum('baixa','media','alta','critica') NOT NULL DEFAULT 'media',
  `titulo` varchar(150) NOT NULL,
  `descricao` text NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `estado` enum('aberta','em_analise','convertida_manutencao','resolvida','rejeitada') NOT NULL DEFAULT 'aberta',
  `avaliado_por_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `avaliado_em` datetime DEFAULT NULL,
  `observacao_gestor` text DEFAULT NULL,
  `manutencao_id` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `ordens_servico`
--

CREATE TABLE `ordens_servico` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `tipo` enum('inspecao','apoio_tecnico','transporte','emergencia','manutencao_externa','outro') NOT NULL DEFAULT 'outro',
  `prioridade` enum('baixa','media','alta','critica') NOT NULL DEFAULT 'media',
  `infraestrutura_id` int(11) DEFAULT NULL,
  `viatura_id` int(10) UNSIGNED DEFAULT NULL,
  `motorista_id` int(10) UNSIGNED DEFAULT NULL,
  `atribuido_por_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `data_prevista` date DEFAULT NULL,
  `hora_prevista` time DEFAULT NULL,
  `inicio_real` datetime DEFAULT NULL,
  `fim_real` datetime DEFAULT NULL,
  `estado` enum('rascunho','atribuida','aceite','em_deslocacao','em_execucao','concluida','impedida','cancelada') NOT NULL DEFAULT 'rascunho',
  `observacoes_operario` text DEFAULT NULL,
  `observacoes_gestor` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos_manutencao`
--

CREATE TABLE `pedidos_manutencao` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `ocorrencia_id` int(10) UNSIGNED DEFAULT NULL,
  `servico_id` int(10) UNSIGNED DEFAULT NULL,
  `viatura_id` int(10) UNSIGNED NOT NULL,
  `motorista_id` int(10) UNSIGNED DEFAULT NULL,
  `criado_por_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `tipo` enum('preventiva','corretiva','inspecao','pneus','oleo','outro') NOT NULL DEFAULT 'outro',
  `prioridade` enum('baixa','media','alta','critica') NOT NULL DEFAULT 'media',
  `descricao` text NOT NULL,
  `pode_circular` tinyint(1) DEFAULT NULL,
  `estado` enum('pendente','aprovado','rejeitado','convertido_manutencao','cancelado') NOT NULL DEFAULT 'pendente',
  `avaliado_por_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `avaliado_em` datetime DEFAULT NULL,
  `motivo_rejeicao` varchar(255) DEFAULT NULL,
  `manutencao_id` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `servicos_operacionais`
--

CREATE TABLE `servicos_operacionais` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `atribuicao_id` int(10) UNSIGNED DEFAULT NULL,
  `motorista_id` int(10) UNSIGNED NOT NULL,
  `colaborador_id` int(10) UNSIGNED DEFAULT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `viatura_id` int(10) UNSIGNED NOT NULL,
  `data_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `data_fim` datetime DEFAULT NULL,
  `km_inicio` int(10) UNSIGNED NOT NULL,
  `km_fim` int(10) UNSIGNED DEFAULT NULL,
  `nivel_combustivel_inicio` tinyint(3) UNSIGNED DEFAULT NULL,
  `nivel_combustivel_fim` tinyint(3) UNSIGNED DEFAULT NULL,
  `estado` enum('aberto','concluido','cancelado') NOT NULL DEFAULT 'aberto',
  `observacoes_inicio` text DEFAULT NULL,
  `observacoes_fim` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `servicos_operacionais`
--

INSERT INTO `servicos_operacionais` (`id`, `codigo`, `atribuicao_id`, `motorista_id`, `colaborador_id`, `usuario_id`, `viatura_id`, `data_inicio`, `data_fim`, `km_inicio`, `km_fim`, `nivel_combustivel_inicio`, `nivel_combustivel_fim`, `estado`, `observacoes_inicio`, `observacoes_fim`, `criado_em`, `atualizado_em`) VALUES
(1, 'SRV-20260521-181846-391', 11, 19, 11, 3, 22, '2026-05-21 17:18:46', '2026-05-21 17:25:07', 68400, 68400, NULL, NULL, 'concluido', NULL, NULL, '2026-05-21 16:18:46', '2026-05-21 16:25:07'),
(2, 'SRV-20260521-182636-301', 11, 19, 11, 3, 22, '2026-05-21 17:26:36', '2026-05-21 22:30:52', 68400, 68400, 80, NULL, 'concluido', NULL, NULL, '2026-05-21 16:26:36', '2026-05-21 21:30:52'),
(3, 'SRV-20260521-233134-602', 11, 19, 11, 3, 22, '2026-05-21 22:31:34', NULL, 68400, NULL, NULL, NULL, 'aberto', NULL, NULL, '2026-05-21 21:31:34', '2026-05-21 21:31:34');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `colaborador_id` int(10) UNSIGNED DEFAULT NULL,
  `nome` varchar(120) NOT NULL,
  `username` varchar(60) NOT NULL,
  `senha` varchar(60) NOT NULL,
  `perfil` varchar(40) NOT NULL DEFAULT 'Gestor',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `motorista_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Ligação ao motorista (para perfil operário)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `colaborador_id`, `nome`, `username`, `senha`, `perfil`, `ativo`, `criado_em`, `motorista_id`) VALUES
(1, NULL, 'Giovanni Santos', 'admin', '$2y$10$I/1aYDnp3bWTXU1C75IzJu1gSOBS0nwoXOXfXGk6/2Ng1ICio6/cq', 'admin', 1, '2026-01-16 23:20:52', NULL),
(2, NULL, 'Alberto Cunha', 'gestor', '$2y$10$oAfkMPLBXZZ7E8RTk67VnuZpvl8e76XjQYy9yRHMBL86whffQF4e2', 'gestor', 1, '2026-02-19 13:35:36', NULL),
(3, 11, 'Inês Carvalho', 'utilizador', '$2y$10$LxUpHyEL.nYq2RXmDm1PW.8wm8KO4ZjczuT0.fM3K482o/BCJ5mMe', 'operario', 1, '2026-02-19 13:36:13', 19);

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
  `estado` enum('Disponível','Atribuída','Em Manutenção','Inativo') NOT NULL DEFAULT 'Disponível',
  `observacoes` text DEFAULT NULL,
  `infraestrutura_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `viaturas`
--

INSERT INTO `viaturas` (`id`, `matricula`, `marca_modelo`, `tipo`, `combustivel`, `quilometragem`, `estado`, `observacoes`, `infraestrutura_id`, `criado_em`, `atualizado_em`) VALUES
(17, 'ABC-1234', 'Mercedes-Benz Sprinter 515', 'Carrinha', 'Diesel', 45230, 'Atribuída', 'Rota operacional - ETAR Zona Norte', 9, '2026-02-12 05:48:09', '2026-03-15 17:44:38'),
(18, 'DEF-5678', 'Toyota Hilux 2.8', 'Pick-up', 'Diesel', 23100, 'Atribuída', 'Rota: Reservatório Central → Bairro Leste', 11, '2026-02-12 05:48:09', '2026-03-15 17:44:38'),
(19, 'JKL-3456', 'Fiat Strada Freedom', 'Pick-up', 'Gasolina', 12050, 'Atribuída', 'Apoio urbano e campo', 9, '2026-02-12 05:48:09', '2026-03-15 17:44:38'),
(20, 'MNO-7890', 'Mercedes-Benz Accelo 815', 'Camião', 'Diesel', 110200, 'Atribuída', 'Transporte interno e apoio logístico', 13, '2026-02-12 05:48:09', '2026-03-15 17:44:38'),
(21, 'STU-4567', 'Volkswagen Delivery 11.180', 'Camião', 'Diesel', 97200, 'Atribuída', 'Distribuição e apoio técnico', 7, '2026-02-12 05:48:09', '2026-03-15 17:44:38'),
(22, 'KLM-5555', 'Renault Kangoo', 'Carrinha', 'Diesel', 68784, 'Atribuída', 'Serviços técnicos - zona oeste', 1, '2026-02-12 05:48:09', '2026-05-21 21:32:38'),
(23, 'NOP-6666', 'Peugeot Partner', 'Carrinha', 'Diesel', 54420, 'Atribuída', 'Equipa de manutenção de rede', 5, '2026-02-12 05:48:09', '2026-03-15 17:44:38'),
(24, 'RST-7777', 'Iveco Daily 35S', 'Carrinha', 'Diesel', 80310, 'Atribuída', 'Apoio operacional - zona sul', 6, '2026-02-12 05:48:09', '2026-03-15 17:44:38'),
(25, 'GHI-9012', 'Ford Cargo 1119', 'Camião', 'Diesel', 89500, 'Em Manutenção', 'Reparo no sistema de freios', 10, '2026-02-12 05:48:09', '2026-03-15 17:44:38'),
(26, 'QWE-2222', 'MAN TGL 8.180', 'Camião', 'Diesel', 121500, 'Em Manutenção', 'Revisão programada (100.000km)', 13, '2026-02-12 05:48:09', '2026-03-15 17:44:38'),
(27, 'ASD-3333', 'Citroën Berlingo', 'Carrinha', 'Diesel', 73800, 'Em Manutenção', 'Troca de embraiagem', 5, '2026-02-12 05:48:09', '2026-03-15 17:44:38'),
(28, 'HJK-4444', 'Isuzu D-Max 3.0', 'Pick-up', 'Diesel', 65800, 'Em Manutenção', 'Suspensão e alinhamento', 7, '2026-02-12 05:48:09', '2026-03-15 17:44:38'),
(29, 'TUV-1111', 'Dacia Duster', 'Ligeiro', 'Gasolina', 28900, 'Disponível', 'Disponível para atribuição', NULL, '2026-02-12 05:48:09', '2026-02-12 05:48:09'),
(30, 'VWX-7788', 'Nissan Leaf', 'Ligeiro', 'Elétrico', 20500, 'Disponível', '0', 3, '2026-02-12 05:48:09', '2026-05-21 13:14:21');

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
  ADD KEY `idx_abast_colab` (`colaborador_id`),
  ADD KEY `idx_abast_motorista_id` (`motorista_id`),
  ADD KEY `idx_abast_usuario_registo` (`registado_por_usuario_id`),
  ADD KEY `idx_abast_estado` (`estado`),
  ADD KEY `idx_abast_data_estado` (`data_abastecimento`,`estado`),
  ADD KEY `fk_abast_usuario_aprovacao` (`aprovado_por_usuario_id`),
  ADD KEY `idx_abast_servico_id` (`servico_id`);

--
-- Índices de tabela `atribuicoes`
--
ALTER TABLE `atribuicoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_atr_viatura` (`viatura_id`),
  ADD KEY `idx_atr_colaborador` (`colaborador_id`),
  ADD KEY `idx_atr_abertas` (`viatura_id`,`data_fim`),
  ADD KEY `idx_atr_motorista_id` (`motorista_id`),
  ADD KEY `idx_atr_estado` (`estado`),
  ADD KEY `idx_atr_motorista_estado` (`motorista_id`,`estado`),
  ADD KEY `idx_atr_viatura_estado` (`viatura_id`,`estado`),
  ADD KEY `fk_atr_criado_usuario` (`criado_por_usuario_id`),
  ADD KEY `fk_atr_encerrado_usuario` (`encerrado_por_usuario_id`);

--
-- Índices de tabela `checklists_servico`
--
ALTER TABLE `checklists_servico`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_checklist_servico_momento` (`servico_id`,`momento`),
  ADD KEY `idx_checklists_servico` (`servico_id`);

--
-- Índices de tabela `colaboradores`
--
ALTER TABLE `colaboradores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `infraestruturas`
--
ALTER TABLE `infraestruturas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `manutencoes`
--
ALTER TABLE `manutencoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_man_viatura` (`viatura_id`),
  ADD KEY `idx_man_status` (`status`),
  ADD KEY `idx_manutencao_pedido` (`pedido_manutencao_id`),
  ADD KEY `idx_manutencao_criado_por` (`criado_por_usuario_id`),
  ADD KEY `idx_manutencao_concluido_por` (`concluido_por_usuario_id`);

--
-- Índices de tabela `motoristas`
--
ALTER TABLE `motoristas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_motoristas_nif` (`nif`),
  ADD KEY `viatura_id` (`viatura_id`),
  ADD KEY `idx_motoristas_colaborador_id` (`colaborador_id`);

--
-- Índices de tabela `ocorrencias`
--
ALTER TABLE `ocorrencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ocorrencias_codigo` (`codigo`),
  ADD KEY `idx_ocorrencias_estado` (`estado`),
  ADD KEY `idx_ocorrencias_gravidade` (`gravidade`),
  ADD KEY `idx_ocorrencias_viatura` (`viatura_id`),
  ADD KEY `idx_ocorrencias_motorista` (`motorista_id`),
  ADD KEY `fk_ocorrencia_servico` (`servico_id`),
  ADD KEY `fk_ocorrencia_usuario_criador` (`criado_por_usuario_id`),
  ADD KEY `fk_ocorrencia_usuario_avaliador` (`avaliado_por_usuario_id`),
  ADD KEY `fk_ocorrencia_manutencao` (`manutencao_id`);

--
-- Índices de tabela `ordens_servico`
--
ALTER TABLE `ordens_servico`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ordens_codigo` (`codigo`),
  ADD KEY `idx_ordens_estado` (`estado`),
  ADD KEY `idx_ordens_motorista_estado` (`motorista_id`,`estado`),
  ADD KEY `idx_ordens_viatura_estado` (`viatura_id`,`estado`),
  ADD KEY `idx_ordens_data_prevista` (`data_prevista`),
  ADD KEY `fk_ordem_infraestrutura` (`infraestrutura_id`),
  ADD KEY `fk_ordem_usuario_atribuicao` (`atribuido_por_usuario_id`);

--
-- Índices de tabela `pedidos_manutencao`
--
ALTER TABLE `pedidos_manutencao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pedidos_manutencao_codigo` (`codigo`),
  ADD KEY `idx_pedidos_estado` (`estado`),
  ADD KEY `idx_pedidos_prioridade` (`prioridade`),
  ADD KEY `idx_pedidos_viatura` (`viatura_id`),
  ADD KEY `idx_pedidos_motorista` (`motorista_id`),
  ADD KEY `fk_pedido_ocorrencia` (`ocorrencia_id`),
  ADD KEY `fk_pedido_servico` (`servico_id`),
  ADD KEY `fk_pedido_usuario_criador` (`criado_por_usuario_id`),
  ADD KEY `fk_pedido_usuario_avaliador` (`avaliado_por_usuario_id`),
  ADD KEY `fk_pedido_manutencao` (`manutencao_id`);

--
-- Índices de tabela `servicos_operacionais`
--
ALTER TABLE `servicos_operacionais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_servicos_codigo` (`codigo`),
  ADD KEY `idx_servicos_motorista_estado` (`motorista_id`,`estado`),
  ADD KEY `idx_servicos_viatura_estado` (`viatura_id`,`estado`),
  ADD KEY `idx_servicos_data_inicio` (`data_inicio`),
  ADD KEY `fk_servico_atribuicao` (`atribuicao_id`),
  ADD KEY `fk_servico_colaborador` (`colaborador_id`),
  ADD KEY `fk_servico_usuario` (`usuario_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_usuarios_colaborador_id` (`colaborador_id`);

--
-- Índices de tabela `viaturas`
--
ALTER TABLE `viaturas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricula` (`matricula`),
  ADD KEY `idx_viaturas_estado` (`estado`),
  ADD KEY `idx_viaturas_tipo` (`tipo`),
  ADD KEY `idx_viaturas_combustivel` (`combustivel`),
  ADD KEY `idx_viaturas_infraestrutura` (`infraestrutura_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `abastecimentos`
--
ALTER TABLE `abastecimentos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `atribuicoes`
--
ALTER TABLE `atribuicoes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `checklists_servico`
--
ALTER TABLE `checklists_servico`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `colaboradores`
--
ALTER TABLE `colaboradores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `infraestruturas`
--
ALTER TABLE `infraestruturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
-- AUTO_INCREMENT de tabela `ocorrencias`
--
ALTER TABLE `ocorrencias`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ordens_servico`
--
ALTER TABLE `ordens_servico`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pedidos_manutencao`
--
ALTER TABLE `pedidos_manutencao`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `servicos_operacionais`
--
ALTER TABLE `servicos_operacionais`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  ADD CONSTRAINT `fk_abast_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_abast_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos_operacionais` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_abast_usuario_aprovacao` FOREIGN KEY (`aprovado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_abast_usuario_registo` FOREIGN KEY (`registado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_abast_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `atribuicoes`
--
ALTER TABLE `atribuicoes`
  ADD CONSTRAINT `fk_atr_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_atr_criado_usuario` FOREIGN KEY (`criado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_atr_encerrado_usuario` FOREIGN KEY (`encerrado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_atr_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_atr_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `checklists_servico`
--
ALTER TABLE `checklists_servico`
  ADD CONSTRAINT `fk_checklist_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos_operacionais` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `manutencoes`
--
ALTER TABLE `manutencoes`
  ADD CONSTRAINT `fk_man_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_manutencao_pedido` FOREIGN KEY (`pedido_manutencao_id`) REFERENCES `pedidos_manutencao` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_manutencao_usuario_conclusao` FOREIGN KEY (`concluido_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_manutencao_usuario_criador` FOREIGN KEY (`criado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `motoristas`
--
ALTER TABLE `motoristas`
  ADD CONSTRAINT `fk_motoristas_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_motoristas_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `ocorrencias`
--
ALTER TABLE `ocorrencias`
  ADD CONSTRAINT `fk_ocorrencia_manutencao` FOREIGN KEY (`manutencao_id`) REFERENCES `manutencoes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ocorrencia_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ocorrencia_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos_operacionais` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ocorrencia_usuario_avaliador` FOREIGN KEY (`avaliado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ocorrencia_usuario_criador` FOREIGN KEY (`criado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ocorrencia_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `ordens_servico`
--
ALTER TABLE `ordens_servico`
  ADD CONSTRAINT `fk_ordem_infraestrutura` FOREIGN KEY (`infraestrutura_id`) REFERENCES `infraestruturas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ordem_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ordem_usuario_atribuicao` FOREIGN KEY (`atribuido_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ordem_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `pedidos_manutencao`
--
ALTER TABLE `pedidos_manutencao`
  ADD CONSTRAINT `fk_pedido_manutencao` FOREIGN KEY (`manutencao_id`) REFERENCES `manutencoes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_ocorrencia` FOREIGN KEY (`ocorrencia_id`) REFERENCES `ocorrencias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos_operacionais` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_usuario_avaliador` FOREIGN KEY (`avaliado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_usuario_criador` FOREIGN KEY (`criado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `servicos_operacionais`
--
ALTER TABLE `servicos_operacionais`
  ADD CONSTRAINT `fk_servico_atribuicao` FOREIGN KEY (`atribuicao_id`) REFERENCES `atribuicoes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servico_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servico_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servico_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_servico_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `viaturas`
--
ALTER TABLE `viaturas`
  ADD CONSTRAINT `fk_viaturas_infraestrutura` FOREIGN KEY (`infraestrutura_id`) REFERENCES `infraestruturas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
