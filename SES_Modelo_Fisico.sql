-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema db_sistema_de_empregabilidade
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema db_sistema_de_empregabilidade
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `db_sistema_de_empregabilidade` DEFAULT CHARACTER SET utf8 ;
USE `db_sistema_de_empregabilidade` ;

-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`pessoa`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`pessoa` (
  `id_pessoa` INT NOT NULL,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `telefone` VARCHAR(11) NOT NULL,
  `senha` VARCHAR(255) NOT NULL,
  `data_cadastro` DATETIME NOT NULL,
  `pessoacol` VARCHAR(45) NULL,
  PRIMARY KEY (`id_pessoa`),
  UNIQUE INDEX `email_UNIQUE` (`email` ASC) VISIBLE,
  UNIQUE INDEX `telefone_UNIQUE` (`telefone` ASC) VISIBLE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`responsavel_contratual`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`responsavel_contratual` (
  `id_responsavel_contratual` INT NOT NULL AUTO_INCREMENT,
  `pessoa_id_pessoa` INT NOT NULL,
  PRIMARY KEY (`id_responsavel_contratual`),
  INDEX `fk_responsavel_contratual_pessoa1_idx` (`pessoa_id_pessoa` ASC) VISIBLE,
  CONSTRAINT `fk_responsavel_contratual_pessoa1`
    FOREIGN KEY (`pessoa_id_pessoa`)
    REFERENCES `db_sistema_de_empregabilidade`.`pessoa` (`id_pessoa`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`empresa`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`empresa` (
  `cnpj` INT NOT NULL,
  `razao_social` VARCHAR(45) NOT NULL,
  `atividade_economica` VARCHAR(45) NOT NULL,
  `pessoa_id_pessoa` INT NOT NULL,
  `responsavel_contratual_id_responsavel_contratual` INT NOT NULL,
  PRIMARY KEY (`cnpj`),
  INDEX `fk_empresa_pessoa1_idx` (`pessoa_id_pessoa` ASC) VISIBLE,
  INDEX `fk_empresa_responsavel_contratual1_idx` (`responsavel_contratual_id_responsavel_contratual` ASC) VISIBLE,
  CONSTRAINT `fk_empresa_pessoa1`
    FOREIGN KEY (`pessoa_id_pessoa`)
    REFERENCES `db_sistema_de_empregabilidade`.`pessoa` (`id_pessoa`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_empresa_responsavel_contratual1`
    FOREIGN KEY (`responsavel_contratual_id_responsavel_contratual`)
    REFERENCES `db_sistema_de_empregabilidade`.`responsavel_contratual` (`id_responsavel_contratual`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`candidato`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`candidato` (
  `matricula` INT NOT NULL,
  `cpf` VARCHAR(14) NOT NULL,
  `status` TINYINT NOT NULL,
  `pessoa_id_pessoa` INT NOT NULL,
  PRIMARY KEY (`matricula`),
  UNIQUE INDEX `cpf_UNIQUE` (`cpf` ASC) VISIBLE,
  INDEX `fk_candidatos_pessoa1_idx` (`pessoa_id_pessoa` ASC) VISIBLE,
  CONSTRAINT `fk_candidatos_pessoa1`
    FOREIGN KEY (`pessoa_id_pessoa`)
    REFERENCES `db_sistema_de_empregabilidade`.`pessoa` (`id_pessoa`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`vagas`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`vagas` (
  `id_vaga` INT NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(100) NOT NULL,
  `tipo` INT NOT NULL,
  `area` VARCHAR(45) NOT NULL,
  `status` TINYINT NOT NULL,
  `data_publicacao` DATE NOT NULL,
  `empresa_cnpj` INT NOT NULL,
  INDEX `fk_vagas_empresa1_idx` (`empresa_cnpj` ASC) VISIBLE,
  PRIMARY KEY (`id_vaga`),
  CONSTRAINT `fk_vagas_empresa1`
    FOREIGN KEY (`empresa_cnpj`)
    REFERENCES `db_sistema_de_empregabilidade`.`empresa` (`cnpj`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`convites`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`convites` (
  `descricao` VARCHAR(150) NOT NULL,
  `data_envio` DATETIME NOT NULL,
  `status` TINYINT NOT NULL,
  `empresa_cnpj` INT NOT NULL,
  `candidatos_matricula` INT NOT NULL,
  `vagas_id_vaga` INT NOT NULL,
  INDEX `fk_convites_empresa1_idx` (`empresa_cnpj` ASC) VISIBLE,
  INDEX `fk_convites_candidatos1_idx` (`candidatos_matricula` ASC) VISIBLE,
  INDEX `fk_convites_vagas1_idx` (`vagas_id_vaga` ASC) VISIBLE,
  CONSTRAINT `fk_convites_empresa1`
    FOREIGN KEY (`empresa_cnpj`)
    REFERENCES `db_sistema_de_empregabilidade`.`empresa` (`cnpj`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_convites_candidatos1`
    FOREIGN KEY (`candidatos_matricula`)
    REFERENCES `db_sistema_de_empregabilidade`.`candidato` (`matricula`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_convites_vagas1`
    FOREIGN KEY (`vagas_id_vaga`)
    REFERENCES `db_sistema_de_empregabilidade`.`vagas` (`id_vaga`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`link_externo`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`link_externo` (
  `linkedin` VARCHAR(100) NULL,
  `portfolio` VARCHAR(100) NULL,
  `github` VARCHAR(100) NULL,
  `candidato_matricula` INT NOT NULL,
  INDEX `fk_link_externo_candidato1_idx` (`candidato_matricula` ASC) VISIBLE,
  CONSTRAINT `fk_link_externo_candidato1`
    FOREIGN KEY (`candidato_matricula`)
    REFERENCES `db_sistema_de_empregabilidade`.`candidato` (`matricula`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`informacoes_profissionais(FR5)`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`informacoes_profissionais(FR5)` (
  `sobre_mim` VARCHAR(200) NULL,
  `cargo_de_interesse` VARCHAR(45) NULL,
  `area_de_atuacao` VARCHAR(45) NOT NULL,
  `habilidades_tags` INT NULL,
  `candidato_matricula` INT NOT NULL,
  INDEX `fk_informacoes_profissionais(FR5)_candidato1_idx` (`candidato_matricula` ASC) VISIBLE,
  CONSTRAINT `fk_informacoes_profissionais(FR5)_candidato1`
    FOREIGN KEY (`candidato_matricula`)
    REFERENCES `db_sistema_de_empregabilidade`.`candidato` (`matricula`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`preferencias_de_trabalho(FR6)`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`preferencias_de_trabalho(FR6)` (
  `tipo_de_contatracao` INT NULL,
  `disponibilidade_de_horario` TIME NOT NULL,
  `regiao_administrativa` VARCHAR(100) NOT NULL,
  `pretencao_salarial` INT NULL,
  `candidato_matricula` INT NOT NULL,
  INDEX `fk_preferencias_de_trabalho(FR6)_candidato1_idx` (`candidato_matricula` ASC) VISIBLE,
  CONSTRAINT `fk_preferencias_de_trabalho(FR6)_candidato1`
    FOREIGN KEY (`candidato_matricula`)
    REFERENCES `db_sistema_de_empregabilidade`.`candidato` (`matricula`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`dados_academicos(SIG)`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`dados_academicos(SIG)` (
  `instituicao` VARCHAR(100) NOT NULL,
  `curso` VARCHAR(45) NOT NULL,
  `unidade` VARCHAR(45) NOT NULL,
  `ano_de_conclusao` DATE NOT NULL,
  `candidato_matricula` INT NOT NULL,
  INDEX `fk_dados_academicos(SIG)_candidato1_idx` (`candidato_matricula` ASC) VISIBLE,
  CONSTRAINT `fk_dados_academicos(SIG)_candidato1`
    FOREIGN KEY (`candidato_matricula`)
    REFERENCES `db_sistema_de_empregabilidade`.`candidato` (`matricula`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`administrativo`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`administrativo` (
  `pessoa_id_pessoa` INT NOT NULL,
  PRIMARY KEY (`pessoa_id_pessoa`),
  INDEX `fk_administrativo_pessoa1_idx` (`pessoa_id_pessoa` ASC) VISIBLE,
  CONSTRAINT `fk_administrativo_pessoa1`
    FOREIGN KEY (`pessoa_id_pessoa`)
    REFERENCES `db_sistema_de_empregabilidade`.`pessoa` (`id_pessoa`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`alunos_migrados`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`alunos_migrados` (
  `status_ativacao` TINYINT NOT NULL,
  `ultima_sincronizacao` DATETIME NULL,
  `administrativo_pessoa_id_pessoa` INT NOT NULL,
  INDEX `fk_alunos_migrados_administrativo1_idx` (`administrativo_pessoa_id_pessoa` ASC) VISIBLE,
  CONSTRAINT `fk_alunos_migrados_administrativo1`
    FOREIGN KEY (`administrativo_pessoa_id_pessoa`)
    REFERENCES `db_sistema_de_empregabilidade`.`administrativo` (`pessoa_id_pessoa`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`engajamento_por_unidade_senac`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`engajamento_por_unidade_senac` (
  `unidade` VARCHAR(100) NOT NULL,
  `elegibilidade` TINYINT NOT NULL,
  `status` TINYINT NOT NULL,
  `administrativo_pessoa_id_pessoa` INT NOT NULL,
  PRIMARY KEY (`unidade`),
  INDEX `fk_engajamento_por_unidade_senac_administrativo1_idx` (`administrativo_pessoa_id_pessoa` ASC) VISIBLE,
  CONSTRAINT `fk_engajamento_por_unidade_senac_administrativo1`
    FOREIGN KEY (`administrativo_pessoa_id_pessoa`)
    REFERENCES `db_sistema_de_empregabilidade`.`administrativo` (`pessoa_id_pessoa`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`historico_de_engajamento`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`historico_de_engajamento` (
  `convites_enviados` INT NOT NULL,
  `contratacoes` INT NOT NULL,
  `empresa_cnpj` INT NOT NULL,
  INDEX `fk_historico_de_engajamento_empresa1_idx` (`empresa_cnpj` ASC) VISIBLE,
  CONSTRAINT `fk_historico_de_engajamento_empresa1`
    FOREIGN KEY (`empresa_cnpj`)
    REFERENCES `db_sistema_de_empregabilidade`.`empresa` (`cnpj`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `db_sistema_de_empregabilidade`.`empresa_has_candidatos`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `db_sistema_de_empregabilidade`.`empresa_has_candidatos` (
  `empresa_cnpj` INT NOT NULL,
  `candidatos_matricula` INT NOT NULL,
  PRIMARY KEY (`empresa_cnpj`, `candidatos_matricula`),
  INDEX `fk_empresa_has_candidatos_candidatos1_idx` (`candidatos_matricula` ASC) VISIBLE,
  INDEX `fk_empresa_has_candidatos_empresa1_idx` (`empresa_cnpj` ASC) VISIBLE,
  CONSTRAINT `fk_empresa_has_candidatos_empresa1`
    FOREIGN KEY (`empresa_cnpj`)
    REFERENCES `db_sistema_de_empregabilidade`.`empresa` (`cnpj`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_empresa_has_candidatos_candidatos1`
    FOREIGN KEY (`candidatos_matricula`)
    REFERENCES `db_sistema_de_empregabilidade`.`candidato` (`matricula`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
