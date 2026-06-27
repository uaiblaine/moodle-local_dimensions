# Design kit — Central de Competências (local_dimensions)

Biblioteca de componentes do redesign administrativo (e base para o plugin companheiro
`local_modfields`). Cada arquivo `.html` é um **preview self-contained** (tokens inline, claro/escuro
via `prefers-color-scheme`) com um marcador `@dsCard` na primeira linha para o índice do Design System.

## Componentes
| Arquivo | Grupo | O que é |
|---|---|---|
| `tokens.html` | Fundações | Paleta, papéis semânticos, tipografia, raio/espaçamento. |
| `modal-shell.html` | Shell | Cabeçalho + corpo + rodapé de modal (base de todo modal `dynamic_form`). |
| `form-section.html` | Formulário | Seção com título + descrição (explicação) e linhas de campo (texto, select, colorpicker). |
| `image-dropzone.html` | Formulário | Anexo de imagem padrão Moodle (arrasta e solta) — vazio e com arquivo. |
| `hierarchy-nav.html` | Navegação | Seletor de contexto (Sistema/Categoria + contador), trilha adaptativa, abas Estrutura/Planos. |
| `master-detail.html` | Dados | Árvore de competências + painel de detalhe; chips de framework. |
| `paginated-picker.html` | Dados | Busca server-side + resultados AJAX + paginação; controle `ruleoutcome`. |
| `cohort-assign.html` | Dados | Atribuição de plano a coortes/usuários (estilo gestão de grupos do mtube) + sync. |

## Como sincronizar com o Claude Design
Neste ambiente (web/agente) o `/design-login` não está disponível. Para sincronizar:
1. Abra um terminal `claude` interativo neste repositório e rode `/design-login`.
2. Use o DesignSync apontando `localDir` para esta pasta: `list_projects` (ou `create_project`) →
   `finalize_plan` (writes = `**/*.html`) → `write_files`.
   Alternativa: importar pela própria UI do Claude Design.

## Mapeamento para código
- Cada componente → um partial **Mustache** + estilos **SCSS (Boost)**; os tokens deste kit → variáveis SCSS.
- Os modais usam `core_form\dynamic_form` via `core_form/modalform`; listas/árvore/picker usam `core/ajax`
  com **paginação server-side** e **lazy-load** (ver `../admin-redesign.md`, seção 9.5).
