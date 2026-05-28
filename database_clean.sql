-- Schema limpo e consolidado para AquaFleet
-- Versão final com infraestrutura_id na tabela usuarios e restrições integradas.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Estrutura para tabela `zonas_operacionais`
--
CREATE TABLE `zonas_operacionais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `cor` varchar(7) DEFAULT '#3b82f6',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `infraestruturas`
--
CREATE TABLE `infraestruturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `tipo` enum('ETA','ETAR') NOT NULL,
  `concelho` varchar(100) DEFAULT NULL,
  `localidade` varchar(150) DEFAULT NULL,
  `sub_regiao` varchar(100) NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `zona_operacional_id` int(11) DEFAULT NULL,
  `precisao_localizacao` enum('aproximada','validada','oficial') DEFAULT 'aproximada',
  `fonte_localizacao` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_infraestruturas_zona` FOREIGN KEY (`zona_operacional_id`) REFERENCES `zonas_operacionais` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `colaboradores`
--
CREATE TABLE `colaboradores` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `cargo` varchar(80) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `frotas`
--
CREATE TABLE `frotas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `zona_operacional_id` int(11) DEFAULT NULL,
  `gestor_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_frotas_zona` FOREIGN KEY (`zona_operacional_id`) REFERENCES `zonas_operacionais` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_frotas_gestor` FOREIGN KEY (`gestor_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `viaturas`
--
CREATE TABLE `viaturas` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `matricula` varchar(20) NOT NULL,
  `marca_modelo` varchar(120) NOT NULL,
  `tipo` enum('Ligeiro','Pick-up','Carrinha','Camião','Elétrico','Outro') NOT NULL DEFAULT 'Ligeiro',
  `combustivel` enum('Diesel','Gasolina','Elétrico','Híbrido','Outro') NOT NULL DEFAULT 'Diesel',
  `quilometragem` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `estado` enum('Disponível','Atribuída','Em Manutenção','Inativo') NOT NULL DEFAULT 'Disponível',
  `observacoes` text DEFAULT NULL,
  `infraestrutura_id` int(11) DEFAULT NULL,
  `zona_operacional_id` int(11) DEFAULT NULL,
  `frota_id` int(11) DEFAULT NULL,
  `lat_atual` decimal(10,8) DEFAULT NULL,
  `lng_atual` decimal(11,8) DEFAULT NULL,
  `data_localizacao` datetime DEFAULT NULL,
  `origem_localizacao` varchar(50) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `matricula` (`matricula`),
  KEY `idx_viaturas_estado` (`estado`),
  KEY `idx_viaturas_infraestrutura` (`infraestrutura_id`),
  CONSTRAINT `fk_viaturas_infraestrutura` FOREIGN KEY (`infraestrutura_id`) REFERENCES `infraestruturas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_viaturas_zona` FOREIGN KEY (`zona_operacional_id`) REFERENCES `zonas_operacionais` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_viaturas_frota` FOREIGN KEY (`frota_id`) REFERENCES `frotas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `motoristas`
--
CREATE TABLE `motoristas` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
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
  `zona_operacional_id` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_motoristas_nif` (`nif`),
  KEY `idx_motoristas_colaborador_id` (`colaborador_id`),
  CONSTRAINT `fk_motoristas_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_motoristas_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_motoristas_zona` FOREIGN KEY (`zona_operacional_id`) REFERENCES `zonas_operacionais` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `usuarios`
--
CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `colaborador_id` int(10) UNSIGNED DEFAULT NULL,
  `nome` varchar(120) NOT NULL,
  `username` varchar(60) NOT NULL,
  `senha` varchar(60) NOT NULL,
  `perfil` varchar(40) NOT NULL DEFAULT 'Gestor',
  `nivel_gestao` varchar(20) DEFAULT NULL,
  `zona_operacional_id` int(11) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `motorista_id` int(10) UNSIGNED DEFAULT NULL,
  `infraestrutura_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_usuarios_colaborador_id` (`colaborador_id`),
  KEY `fk_usuarios_infraestrutura` (`infraestrutura_id`),
  CONSTRAINT `fk_usuarios_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_usuarios_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_usuarios_infraestrutura` FOREIGN KEY (`infraestrutura_id`) REFERENCES `infraestruturas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_usuarios_zona` FOREIGN KEY (`zona_operacional_id`) REFERENCES `zonas_operacionais` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `localizacoes_viaturas`
--
CREATE TABLE `localizacoes_viaturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `viatura_id` int(10) UNSIGNED NOT NULL,
  `motorista_id` int(10) UNSIGNED DEFAULT NULL,
  `servico_id` int(10) UNSIGNED DEFAULT NULL,
  `origem` varchar(50) NOT NULL,
  `latitude` decimal(10, 8) NOT NULL,
  `longitude` decimal(11, 8) NOT NULL,
  `data_hora` datetime DEFAULT CURRENT_TIMESTAMP,
  `observacoes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_lv_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lv_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_lv_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos_operacionais` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `atribuicoes`
--
CREATE TABLE `atribuicoes` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
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
  `encerrado_por_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_atr_viatura` (`viatura_id`),
  KEY `idx_atr_colaborador` (`colaborador_id`),
  KEY `idx_atr_motorista_id` (`motorista_id`),
  KEY `idx_atr_estado` (`estado`),
  CONSTRAINT `fk_atr_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_atr_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_atr_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_atr_criado_usuario` FOREIGN KEY (`criado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_atr_encerrado_usuario` FOREIGN KEY (`encerrado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `servicos_operacionais`
--
CREATE TABLE `servicos_operacionais` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
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
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_servicos_codigo` (`codigo`),
  CONSTRAINT `fk_servico_atribuicao` FOREIGN KEY (`atribuicao_id`) REFERENCES `atribuicoes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_servico_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_servico_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_servico_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_servico_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `checklists_servico`
--
CREATE TABLE `checklists_servico` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
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
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_checklist_servico_momento` (`servico_id`,`momento`),
  CONSTRAINT `fk_checklist_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos_operacionais` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `pedidos_manutencao`
--
CREATE TABLE `pedidos_manutencao` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) NOT NULL,
  `ocorrencia_id` int(10) UNSIGNED DEFAULT NULL,
  `servico_id` int(10) UNSIGNED DEFAULT NULL,
  `viatura_id` int(10) UNSIGNED NOT NULL,
  `motorista_id` int(10) UNSIGNED DEFAULT NULL,
  `criado_por_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `tipo` enum('Preventiva','Corretiva','Inspeção','Pneus','Óleo','Outro') NOT NULL DEFAULT 'Outro',
  `prioridade` enum('baixa','media','alta','critica') NOT NULL DEFAULT 'media',
  `descricao` text NOT NULL,
  `pode_circular` tinyint(1) DEFAULT NULL,
  `estado` enum('pendente','aprovado','rejeitado','convertido_manutencao','cancelado') NOT NULL DEFAULT 'pendente',
  `avaliado_por_usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `avaliado_em` datetime DEFAULT NULL,
  `motivo_rejeicao` varchar(255) DEFAULT NULL,
  `manutencao_id` int(10) UNSIGNED DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pedidos_manutencao_codigo` (`codigo`),
  CONSTRAINT `fk_pedido_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pedido_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pedido_usuario_criador` FOREIGN KEY (`criado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pedido_usuario_avaliador` FOREIGN KEY (`avaliado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `manutencoes`
--
CREATE TABLE `manutencoes` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
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
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_man_viatura` (`viatura_id`),
  KEY `idx_man_status` (`status`),
  CONSTRAINT `fk_man_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_manutencao_pedido` FOREIGN KEY (`pedido_manutencao_id`) REFERENCES `pedidos_manutencao` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_manutencao_usuario_conclusao` FOREIGN KEY (`concluido_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_manutencao_usuario_criador` FOREIGN KEY (`criado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `ocorrencias`
--
CREATE TABLE `ocorrencias` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
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
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ocorrencias_codigo` (`codigo`),
  CONSTRAINT `fk_ocorrencia_manutencao` FOREIGN KEY (`manutencao_id`) REFERENCES `manutencoes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ocorrencia_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ocorrencia_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos_operacionais` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ocorrencia_usuario_avaliador` FOREIGN KEY (`avaliado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ocorrencia_usuario_criador` FOREIGN KEY (`criado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ocorrencia_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `abastecimentos`
--
CREATE TABLE `abastecimentos` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
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
  `longitude` decimal(10,7) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_abast_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `colaboradores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_abast_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_abast_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos_operacionais` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_abast_usuario_aprovacao` FOREIGN KEY (`aprovado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_abast_usuario_registo` FOREIGN KEY (`registado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_abast_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Estrutura para tabela `ordens_servico`
--
CREATE TABLE `ordens_servico` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
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
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ordens_codigo` (`codigo`),
  CONSTRAINT `fk_ordem_infraestrutura` FOREIGN KEY (`infraestrutura_id`) REFERENCES `infraestruturas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ordem_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ordem_usuario_atribuicao` FOREIGN KEY (`atribuido_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ordem_viatura` FOREIGN KEY (`viatura_id`) REFERENCES `viaturas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dados Básicos e Sementes
--
INSERT INTO `zonas_operacionais` (`id`, `nome`, `descricao`, `cor`, `ativo`) VALUES
(1, 'Barlavento', 'Zona Oeste do Algarve', '#3b82f6', 1),
(2, 'Centro', 'Zona Central do Algarve', '#10b981', 1),
(3, 'Sotavento', 'Zona Leste do Algarve', '#f59e0b', 1);

INSERT INTO `infraestruturas` (`id`, `nome`, `tipo`, `concelho`, `localidade`, `sub_regiao`, `latitude`, `longitude`, `zona_operacional_id`, `ativo`) VALUES
(1, 'ETA de Alcantarilha', 'ETA', 'Silves', 'Alcantarilha', 'Albufeira / Silves', 37.1306000, -8.3465000, 1, 1),
(2, 'ETA de Fontaínhas', 'ETA', 'Portimão', 'Mexilhoeira Grande', 'Lagos / Portimão', 37.1904000, -8.6248000, 1, 1);

INSERT INTO `viaturas` (`id`, `matricula`, `marca_modelo`, `tipo`, `combustivel`, `estado`, `quilometragem`, `zona_operacional_id`) VALUES
(1, 'AA-11-BB', 'Renault Kangoo', 'Carrinha', 'Diesel', 'Disponível', 0, 1),
(2, 'CC-22-DD', 'Peugeot Partner', 'Carrinha', 'Diesel', 'Disponível', 0, 2),
(3, 'EE-33-FF', 'Citroen Berlingo', 'Carrinha', 'Diesel', 'Disponível', 0, 3);

INSERT INTO `motoristas` (`id`, `nome`, `email`, `telefone`, `status`, `zona_operacional_id`) VALUES
(1, 'Operário Barlavento', 'operario_barla@aqua.pt', '910000001', 'Ativo', 1),
(2, 'Operário Centro', 'operario_centro@aqua.pt', '910000002', 'Ativo', 2),
(3, 'Operário Sotavento', 'operario_sota@aqua.pt', '910000003', 'Ativo', 3);

INSERT INTO `usuarios` (`id`, `colaborador_id`, `nome`, `username`, `senha`, `perfil`, `nivel_gestao`, `zona_operacional_id`, `ativo`, `motorista_id`, `infraestrutura_id`) VALUES
(1, NULL, 'Giovanni Santos', 'admin', '$2y$10$I/1aYDnp3bWTXU1C75IzJu1gSOBS0nwoXOXfXGk6/2Ng1ICio6/cq', 'admin', 'global', NULL, 1, NULL, NULL),
(2, NULL, 'Alberto Cunha', 'gestor', '$2y$10$oAfkMPLBXZZ7E8RTk67VnuZpvl8e76XjQYy9yRHMBL86whffQF4e2', 'gestor', 'zona', 1, 1, NULL, 1),
(3, NULL, 'Gestor Centro', 'gestor_centro', '$2y$10$I/1aYDnp3bWTXU1C75IzJu1gSOBS0nwoXOXfXGk6/2Ng1ICio6/cq', 'gestor', 'zona', 2, 1, NULL, NULL),
(4, NULL, 'Gestor Sotavento', 'gestor_sotavento', '$2y$10$I/1aYDnp3bWTXU1C75IzJu1gSOBS0nwoXOXfXGk6/2Ng1ICio6/cq', 'gestor', 'zona', 3, 1, NULL, NULL),
(5, NULL, 'Operário Barlavento', 'operario_barla', '$2y$10$I/1aYDnp3bWTXU1C75IzJu1gSOBS0nwoXOXfXGk6/2Ng1ICio6/cq', 'operario', 'nenhum', 1, 1, 1, NULL),
(6, NULL, 'Operário Centro', 'operario_centro', '$2y$10$I/1aYDnp3bWTXU1C75IzJu1gSOBS0nwoXOXfXGk6/2Ng1ICio6/cq', 'operario', 'nenhum', 2, 1, 2, NULL),
(7, NULL, 'Operário Sotavento', 'operario_sota', '$2y$10$I/1aYDnp3bWTXU1C75IzJu1gSOBS0nwoXOXfXGk6/2Ng1ICio6/cq', 'operario', 'nenhum', 3, 1, 3, NULL);

COMMIT;
