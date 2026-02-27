# Documentação dos Arquivos - Sistema de Cotações

Esta documentação lista todos os arquivos encontrados no diretório `/sistema-cotacoes` para auxiliar na limpeza e organização do projeto.

## Legenda de Status
- **Manter**: Arquivo essencial para o funcionamento do sistema.
- **Verificar**: Arquivo que parece ser um script de correção ou migração antiga. Verificar se ainda é necessário.
- **Deletar/Avaliar**: Arquivo de teste, debug, backup ou log que provavelmente pode ser removido.

---

## 1. Páginas Principais (Frontend)
Arquivos acessados diretamente pelos usuários ou que compõem a interface visual.

| Arquivo | Descrição | Status Sugerido |
| :--- | :--- | :--- |
| `index.html` | Página de login/entrada (HTML puro). Redireciona para o fluxo de autenticação. | **Manter** |
| `login.php` | Processamento do login e criação de sessão. | **Manter** |
| `logout.php` | Encerra a sessão do usuário. | **Manter** |
| `aviso.php` | Tela de aviso de compliance/confidencialidade exibida antes de entrar no sistema. | **Manter** |
| `central_gerenciamento.php` | Painel administrativo principal. Links para gerenciar usuários, produtos, etc. | **Manter** |
| `bi.php` | Dashboard de Business Intelligence (KPIs e Gráficos). | **Manter** |
| `pesquisar.php` | Tela principal de pesquisa de cotações/orçamentos. | **Manter** |
| `consultar_orcamentos.php` | Lista e consulta de orçamentos existentes. | **Manter** |
| `incluir_orcamento.php` | Formulário principal para criação de novos orçamentos. | **Manter** |
| `editar_cenario.php` | Edição de cenários de cotação. | **Manter** |
| `gerenciar_usuarios.php` | CRUD de usuários. | **Manter** |
| `gerenciar_produtos.php` | CRUD de produtos. | **Manter** |
| `gerenciar_cliente.php` | CRUD de clientes. | **Manter** |
| `gerenciar_fornecedores.php`| CRUD de fornecedores. | **Manter** |
| `cadastro.php` | Formulário de cadastro de novos usuários (externo/inicial). | **Manter** |
| `previsao.php` | Funcionalidade relacionada a previsão/forecast (parece ser módulo financeiro/vendas). | **Manter** |
| `nfs_emitidas.php` | Consulta de Notas Fiscais emitidas. | **Manter** |
| `header.php` | Cabeçalho padrão incluído em todas as páginas (Menu, CSS, Scripts). | **Manter** |

## 2. Scripts de Processamento (Backend)
Scripts que recebem dados de formulários ou requisições AJAX e interagem com o banco de dados.

| Arquivo | Descrição | Status Sugerido |
| :--- | :--- | :--- |
| `conexao.php` | Arquivo de configuração de conexão com o Banco de Dados. | **Manter** (Essencial) |
| `salvar_orcamento.php` | Processa a criação/edição de orçamentos. | **Manter** |
| `salvar_cenario.php` | Salva alterações em cenários. | **Manter** |
| `salvar_produto.php` | Salva/Edita produtos. | **Manter** |
| `salvar_usuario.php` | Salva novos usuários (autocadastro). | **Manter** |
| `salvar_usuario_admin.php` | Salva usuários via painel administrativo. | **Manter** |
| `salvar_item.php` | Salva itens individuais de um orçamento. | **Manter** |
| `salvar_permissoes_menu.php`| Salva as permissões de acesso aos menus. | **Manter** |
| `processa_pedido_amostra.php`| Processa pedidos de amostra. | **Manter** |
| `processa_alteracao_amostra.php` | Processa alterações em pedidos de amostra. | **Manter** |
| `atualizar_orcamento.php` | Script específico para updating de orçamentos. | **Manter** |
| `atualizar_cenario.php` | Atualiza dados de um cenário específico. | **Manter** |
| `atualizar_produto.php` | Atualização rápida de produtos (provavelmente AJAX). | **Manter** |
| `atualizar_status_amostra.php` | Atualiza status de amostras via AJAX/Listagem. | **Manter** |
| `excluir_orcamento.php` | Deleta orçamentos. | **Manter** |
| `excluir_orcamento_item.php`| Deleta um item específico de um orçamento. | **Manter** |
| `excluir_cenario.php` | Deleta cenários. | **Manter** |
| `excluir_produto.php` | Deleta produtos. | **Manter** |
| `excluir_usuario.php` | Deleta usuários. | **Manter** |
| `excluir_amostra.php` | Deleta solicitações de amostra. | **Manter** |
| `buscar_clientes.php` | API/AJAX para buscar clientes (autocomplete). | **Manter** |
| `buscar_produtos.php` | API/AJAX para buscar produtos. | **Manter** |
| `buscar_icms.php` | Busca alíquotas de ICMS. | **Manter** |
| `buscar_clientes_amostra.php`| Busca específica de clientes para modulo de amostras. | **Manter** |
| `buscar_historico_produto.php`| Busca histórico de preços de produtos. | **Manter** |
| `filtrar.php` | Lógica de filtro para listagens (ou redirecionador pós-login). | **Manter** |
| `filtrar_amostras.php` | Filtro específico para lista de amostras. | **Manter** |
| `gerar_hash.php` | Utilitário para gerar hash de senhas (provavelmente usado manualmente ou em testes). | **Verificar** |

## 3. Módulo de Amostras
Arquivos específicos do fluxo de solicitação de amostras.

| Arquivo | Descrição | Status Sugerido |
| :--- | :--- | :--- |
| `pesquisar_amostras.php` | Lista/Pesquisa de amostras solicitadas. | **Manter** |
| `incluir_ped_amostras.php` | Formulário para nova solicitação de amostra. | **Manter** |
| `consultar_amostras.php` | Consulta detalhes de amostras. | **Manter** |
| `alterar_amostra.php` | Edição de amostras. | **Manter** |
| `gerar_pdf_amostra.php` | Gera PDF da solicitação de amostra. | **Manter** |

## 4. PDFs e Exportações
Geração de documentos.

| Arquivo | Descrição | Status Sugerido |
| :--- | :--- | :--- |
| `gerar_pdf.php` | Gerador genérico de PDF (verificar uso). | **Manter** |
| `gerar_pdf_orcamento.php` | PDF padrão de orçamento. | **Manter** |
| `gerar_pdf_orcamento_net.php`| PDF de orçamento com preços NET. | **Manter** |
| `gerar_pdf_cenario.php` | PDF de cenários de cotação. | **Manter** |
| `gerar_pdf_oc.php` | PDF de Ordem de Compra (OC). | **Manter** |
| `pdf_generator.php` | Classe ou biblioteca encapsulada para geração de PDFs. | **Manter** |
| `exportar_excel.php` | Exportação de dados para Excel. | **Manter** |
| `exportar_bi.php` | Exportação dos dados do Dashboard BI. | **Manter** |
| `download_template_price_list.php` | Baixa o modelo CSV para importação de preços. | **Manter** |

## 5. Configurações e Helpers
Bibliotecas e configurações do sistema.

| Arquivo | Descrição | Status Sugerido |
| :--- | :--- | :--- |
| `config_email.php` | Configurações de envio de e-mail (SMTP). | **Manter** |
| `config_graph.php` | Configurações para gráficos (usado no BI?). | **Manter** |
| `LayoutHelper.php` | Classe auxiliar para renderização de layouts HTML. | **Manter** |
| `GraphMailer.php` | Helper para enviar gráficos por e-mail. | **Manter** |
| `ptax.php` | Consulta de taxa PTAX (Dólar). | **Manter** |
| `check_time.php` | Script simples de verificação de horário (timezone). | **Verificar** |

## 6. Scripts de Importação e Migração
Ferramentas de importação de dados e correção de banco de dados. Mantenha com cuidado, mas muitos podem ser antigos.

| Arquivo | Descrição | Status Sugerido |
| :--- | :--- | :--- |
| `importar_price_list.php` | Importação de lista de preços via CSV/Excel. | **Manter** |
| `importar_fornecedores.php` | Importação massiva de fornecedores. | **Manter** |
| `importar_fornecedores_manual.php` | Importação manual (upload?). | **Manter** |
| `importar_fornecedores_bling.php` | Integração com Bling para fornecedores. | **Manter** |
| `migrar_cenarios.php` | Script de migração de estrutura de dados de cenários (Versão Antiga). | **Verificar/Deletar**  |
| `migrar_cliente_para_itens.php`| Migração de dados de clientes para tabela de itens. | **Verificar/Deletar** |
| `migrar_spec_exclusiva.php` | Migração de especificações. | **Verificar/Deletar** |
| `migrar_tempo_para_itens.php` | Migração de campos de tempo. | **Verificar/Deletar** |
| `setup_cenarios.php` | Criação/Setup de tabelas de cenários. | **Verificar** |
| `fix_db_columns.php` | Correção de colunas no DB. | **Verificar/Deletar** |
| `fix_db_oc_id.php` | Correção de IDs de OCs. | **Verificar/Deletar** |
| `fix_db_schema_amostra.php` | Correção de schema de amostras. | **Verificar/Deletar** |
| `fix_oc_columns.php` | Correção de colunas de OC. | **Verificar/Deletar** |
| `update_db_cenarios_v2.php` | Update de banco de dados (versão 2). | **Verificar/Deletar** |
| `update_db_cenarios_v3.php` | Update de banco de dados (versão 3). | **Verificar/Deletar** |
| `update_db_gestao.php` | Update do módulo de gestão. | **Verificar/Deletar** |
| `update_db_modal.php` | Update relacionado a modais. | **Verificar/Deletar** |
| `update_db_oc.php` | Update de OCs. | **Verificar/Deletar** |
| `update_db_price_list.php` | Update para Price List. | **Verificar/Deletar** |
| `check_oc_schema.php` | Verificador de schema de OC. | **Verificar/Deletar** |
| `check_table_schema.php` | Verificador de tabelas genérico. | **Verificar/Deletar** |
| `check_pdf_requirements.php` | Verifica requisitos para PDF. | **Verificar** |
| `check_suframa.php` | Validador/Checker de Suframa. | **Verificar** |
| `migration_add_cost.php` | Adiciona coluna de custo. | **Verificar/Deletar** |
| `dummy_truncate.php` | Script vazio ou de limpeza manual. | **Deletar** |

## 7. Arquivos de Teste, Debug e Lixo (Candidatos Fortes a Exclusão)
Arquivos que provavelmente não fazem parte do sistema em produção.

| Arquivo | Descrição | Status Sugerido |
| :--- | :--- | :--- |
| `teste.php` | Arquivo de teste genérico. | **Deletar** |
| `teste.envio.php` | Teste de envio (email/form). | **Deletar** |
| `teste_pdf.php` | Teste de geração de PDF. | **Deletar** |
| `teste_conexao_hostinger.php` | Teste de conectividade com Hostinger. | **Deletar** |
| `treste.api.php` | Ferramenta de importação/teste da API Mainô (Typo no nome). | **Deletar/Mover para dev** |
| `test_curl.php` | Teste de cURL. | **Deletar** |
| `test_write.php` | Teste de permissão de escrita. | **Deletar** |
| `test_write_perm.php` | Teste de permissão de escrita. | **Deletar** |
| `test_date_insert.php` | Teste de inserção de data. | **Deletar** |
| `debug.php` | Script de debug. | **Deletar** |
| `debug_repro.php` | Reprodução de erro para debug. | **Deletar** |
| `debug_schema.php` | Debug de schema de banco. | **Deletar** |
| `debug_full.txt` | Log de debug (TXT). | **Deletar** |
| `debug_test_write.txt` | Arquivo gerado por teste de escrita. | **Deletar** |
| `erroslog.txt` | Log de erros. | **Deletar/Arquivar** |
| `probe_api_nfs.php` | Script "sonda" para testar API de NFs. | **Deletar** |
| `probe_api_pedidos.php` | Script "sonda" para testar API de Pedidos. | **Deletar** |
| `probe_faturado.php` | Script "sonda" de faturamento. | **Deletar** |
| `probe_filter_pedido.php` | Script "sonda" de filtro. | **Deletar** |
| `probe_match.php` | Script "sonda" de match. | **Deletar** |
| `inspect_item.php` | Inspeção de itens (debug). | **Deletar** |
| `inspect_json.php` | Inspeção de JSON. | **Deletar** |
| `pesquisar - Copia.php` | Backup manual do arquivo pesquisar.php. | **Deletar** |

## 8. Arquivos de Dados e Outros
| Arquivo | Descrição | Status Sugerido |
| :--- | :--- | :--- |
| `BASE FORNECEDORES.xlsx` | Planilha Excel (provavelmente upload ou base para importação). | **Deletar/Arquivar** |
| `price_list.csv` | CSV de lista de preços (provavelmente upload recente). | **Verificar** |
| `body.json` | Dump grande de JSON. | **Deletar** |
| `faturado_sample.json` | Amostra de JSON. | **Deletar** |
| `nfs_sample.json` | Amostra de JSON. | **Deletar** |
| `nfs_real_sample.json` | Amostra real de JSON. | **Deletar** |
| `pedidos_sample.json` | Amostra de JSON. | **Deletar** |
| `relatório_contatos_*.csv` | Relatório exportado em CSV. | **Deletar** |
| `setup_cenarios_db.sql` | Dump SQL para setup. | **Arquivar** |
| `last_upload_price_list.txt`| Log da data do último upload de price list. | **Manter** |
| `fix_modal_cliente.txt` | Snippet de código em texto. | **Deletar** |
| `cenario_fix.js` | Snippet de correção JS (verificar se foi integrado ao `cenario_script.js`). | **Deletar** |
