# Plano de código — Central de Competências (local_dimensions)

> Blueprint de implementação da admin modal/superfície única. Companheiro de
> [`admin-redesign.md`](admin-redesign.md) (IA, catálogo de campos, performance) e do
> [`design-kit/`](design-kit/) (componentes). **Design-only nesta rodada** — descreve o que construir;
> nenhum código de produção é alterado agora.
>
> Princípios herdados: reorganizar o que já existe (sem features novas, salvo expor o vínculo
> competência↔atividade do core); **paginação/AJAX/lazy-load em tudo**; reaproveitar external functions,
> `customfields_io` e handlers atuais.

## 1. Mapa de camadas

| Camada | Onde | Papel |
|---|---|---|
| Página host | `central.php` | `admin_externalpage_setup('local_dimensions_central')` + render do shell `core/dynamic_tabs`. |
| Abas | `classes/output/dynamictabs/{estrutura,planos,frameworks}.php` | Estendem `\core\output\dynamic_tabs\base`: `export_for_template` (payload mínimo) + `get_template` + `is_available` + `js_call_amd` (re-anexa JS a cada refresh). Espelham `format_mtube\output\dynamictabs\participants\groups`. |
| Forms modais | `classes/form/{competency,template,framework}_dynamic_form.php` + pickers | `\core_form\dynamic_form` abertos por `core_form/modalform`. `process_dynamic_submission` reusa as external functions + `customfields_io`. |
| External functions | `classes/external/*` + `db/services.php` (`ajax=>true`, capabilities) | Leitura paginada (árvore, busca, listas, picker, coortes) e escrita (vínculos, ruleoutcome, template-competency, template-cohort). |
| AMD | `amd/src/central/*.js` | Orquestrador de abas, árvore lazy, picker paginado, modais, coortes. Usa `core/ajax`, `core/templates`, `core/str`, `core_form/modalform`, `core/dynamic_tabs`. |
| Templates | `templates/central/*.mustache` | Partials derivados do `design-kit/`. |
| Estilos | SCSS via pipeline atual (`scss_manager`/styles) | Tokens do kit → variáveis; componentes do kit → SCSS. |

## 2. Reuso (não reescrever)
- **External functions atuais**: `local_dimensions_{create,update,read}_{competency,template}` (já ajax + customfields), `_get_competency_courses`, `_get_competency_rule_data`, `_get_fontawesome_icons`.
- **Trait** `external/customfields_io.php` (round-trip text/select/textarea; reincluir picture via draft-area no modal — ver `group_icon_form`).
- **Handlers** `customfield/{competency_handler,lp_handler}.php` + catálogo real de campos (ver `admin-redesign.md` §9.3).
- **Caches MUC** existentes (`competency_metadata_cache`, `template_metadata_cache`, `template_course_cache`, `plan_trail_cache`), `picture_manager`, observers de invalidação, datasources de reportbuilder.

## 3. Novas external functions (paginadas, capability-checked)
Padrão: validar contexto, `require_capability`, retornar `{items, total}` com `limitfrom`/`limitnum`, payload mínimo, sem N+1 (`get_fast_modinfo`, queries em lote).

| Função | Tipo | Capability | Notas |
|---|---|---|---|
| `get_competency_tree` | read | competencyview | Lazy: por `frameworkid` + `parentid` (filhos sob demanda) + flag de "tem filhos". |
| `search_competencies` | read | competencyview | Busca server-side por framework ou cross-framework; retorna nome, path, `frameworkidnumber` (tag), paginado. |
| `get_category_counts` | read | competency/templateview | Contagem por categoria: frameworks (Estrutura) / planos (Planos) — alimenta o seletor de contexto. |
| `list_templates` | read | templateview | Por contexto + busca + **filtro por `competencyid`** (planos que contêm a competência), paginado. |
| `get_template_competencies` | read | templateview | Competências do plano com tag de framework de origem (cross-framework). |
| `add_template_competency` / `remove_template_competency` | write | templatemanage | Sobre `core_competency\api` (template_competency). |
| `get_competency_links` | read | competencyview | Cursos + módulos vinculados + `ruleoutcome` de cada um. |
| `set_competency_links` | write | competencymanage (+ curso) | Vincular/desvincular curso e atividade **com `ruleoutcome`**; envolve `core_competency\api::add_competency_to_course(_module)`, `remove_*`, `set_*_ruleoutcome` (confirmar nomes exatos). |
| `list_cohorts` | read | templatemanage | Coortes paginadas + busca + contagem de membros. |
| `set_template_cohorts` | write | templatemanage | Vincular coortes + **sync** (template_cohort + criação/manutenção de planos; usar a API/tarefa de sync do core — confirmar). |

Core a embrulhar (verificados / a confirmar): `core_competency\api::add_competency_to_course_module` (api.php:1453), `remove_competency_from_course_module` (1496), `list_course_module_competencies` (1241); equivalentes de curso; `set_*_ruleoutcome`; `create_template_cohort`/`delete_template_cohort` + criação de planos por coorte.

## 4. Componentes do kit → mustache/SCSS
| Kit | Mustache | Observação |
|---|---|---|
| `modal-shell` | (via `core_form/modalform` chrome) | Header/footer do dynamic_form. |
| `form-section` | `central/form_section` | Seção título+descrição; envolve campos do handler. |
| `image-dropzone` | nativo `filemanager`/`filepicker` | Draft-area; arrasta-e-solta nativo (funciona em modal). |
| `hierarchy-nav` | `central/contextbar`, `central/rail`, `central/tabs` | Seletor de contexto + contador, trilha adaptativa, abas. |
| `master-detail` | `central/tree`, `central/detail` | Árvore lazy + detalhe; chips em `central/framework_chip`. |
| `paginated-picker` | `central/picker` | Busca + resultados AJAX + paginação. |
| `cohort-assign` | `central/cohort_assign` | Abas Coortes/Usuários + sync. |

## 5. Roadmap incremental
1. **Fase 0 — Andaime.** `central.php` + shell `core/dynamic_tabs` com uma aba; `amd/src/central/tabs.js`; capability/contexto.
2. **Fase 1 — Estrutura (POC).** Aba `estrutura` + `central/tree` com **lazy-load** (`get_competency_tree`) + busca (`search_competencies`); `competency_dynamic_form` em modal reusando `create/update/read_competency` + `customfields_io` (com os campos reais e a seção de Regra **só quando há filhas**). Valida o padrão ponta-a-ponta.
3. **Fase 2 — Vínculo competência↔curso/atividade.** Modal picker (`central/picker`) + `get_competency_links`/`set_competency_links` com **`ruleoutcome`** por vínculo.
4. **Fase 3 — Planos.** Aba `planos` + `list_templates` (busca por competência) + `template_dynamic_form` + picker cross-framework (`search_competencies` + `add/remove_template_competency`).
5. **Fase 4 — Atribuição a coortes/usuários.** `central/cohort_assign` + `list_cohorts`/`set_template_cohorts` com **sync** (sem "colar lista").
6. **Fase 5 — Frameworks + acabamento.** Aba `frameworks`; estado de UI client-side; a11y; endurecimento de performance.

## 6. Checklist de performance
- Toda lista/picker/coorte com **paginação server-side** (`{items,total}`, `limitfrom`/`limitnum`).
- Busca **AJAX debounced**; nunca carregar a árvore/listas inteiras no cliente.
- Árvore **lazy** (filhos ao expandir); busca retorna apenas o necessário.
- WS enxutos: payload mínimo, **capability + `validate_context`**, **sem N+1** (`get_fast_modinfo`, `get_records_sql` em lote), reusar **MUC caches**.
- Re-render de aba por `getContent` (`core_dynamic_tabs_get_content`), não reload.

## 7. Riscos / pontos de atenção
- **Árvore e regras**: hoje `tool_lp/tree` e `tool_lp/competencyruleconfig` foram feitos para página — adaptar a modal com re-init a cada refresh, ou modelo alternativo (começar pela Estrutura como POC).
- **Picture customfield em modal**: usar draft-area como `group_icon_form`; lembrar do "builtin mode" (imagens fora de customfield).
- **Estado de UI** (contexto/framework/busca/aba) migra da URL para client-side (sessionStorage, como o mtube nas subtabs).
- **Nomes exatos de API do core_competency** (ruleoutcome, template_cohort/sync) a confirmar na implementação.

## 8. Testes
- **PHPUnit**: cada external function (paginação, capabilities, contexto, round-trip de customfields, vínculo curso/módulo + ruleoutcome).
- **Behat**: fluxos modais (criar/editar competência, vincular a curso+atividade, montar plano, atribuir coorte) sem reload.
- **A11y**: foco em modal, navegação por teclado, contraste; `moodle-accessibility`/Pa11y.

## 9. Arquivos a criar/modificar (resumo)
Criar: `central.php`; `classes/output/dynamictabs/{estrutura,planos,frameworks}.php`;
`classes/form/{competency,template,framework}_dynamic_form.php`;
`classes/external/{get_competency_tree,search_competencies,get_category_counts,list_templates,get_template_competencies,add_template_competency,remove_template_competency,get_competency_links,set_competency_links,list_cohorts,set_template_cohorts}.php`;
`amd/src/central/{tabs,tree,picker,modals,cohort}.js`; `templates/central/*.mustache`.
Modificar: `db/services.php` (registrar as novas WS), `settings.php` (entrada da Central), SCSS.
Reusar sem alterar: external functions de CRUD + `customfields_io` + handlers + caches.
