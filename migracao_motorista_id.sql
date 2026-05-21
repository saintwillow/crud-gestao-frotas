-- Migração: adicionar motorista_id à tabela usuarios
-- Execute este ficheiro se já tiver a base de dados instalada

ALTER TABLE `usuarios`
  ADD COLUMN `motorista_id` INT(10) UNSIGNED DEFAULT NULL
    COMMENT 'Ligação ao motorista (para perfil operário)'
    AFTER `ativo`;

ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_motorista`
    FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- Também aumentar o tamanho do campo senha para suportar bcrypt (255 chars)
ALTER TABLE `usuarios` MODIFY `senha` VARCHAR(255) NOT NULL;
