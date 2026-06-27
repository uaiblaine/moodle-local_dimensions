# Redesign da interface administrativa do local_dimensions — admin modal/fluída

> **Status:** Documento de design (não há código de produção desta proposta ainda).
> **Origem:** estudo do `format_mtube` como modelo de aprendizagem para uma admin sem recarga de página.
> **Escopo:** desenhar a reconstrução da parte administrativa do Dimensions e catalogar os padrões Moodle
> reutilizáveis. A camada de back-end atual (external functions + handlers de customfield) é amplamente
> reaproveitada — o trabalho é majoritariamente de front-end + camada de formulário.

---

## 1. Por que mudar

Hoje a admin do Dimensions é **página-por-ação**: `manage_competencies.php` → recarga → `edit_competency.php`
→ submit → recarga → volta. Toda operação de CRUD navega para fora e volta. Além disso, **competências só se
vinculam a cursos** (tabela core `competency_coursecomp`) e o vínculo é feito **dentro do curso** — muitas telas,
muitos caminhos. Não há vínculo competência↔atividade.

O objetivo é uma **"Central de Competências"** em contexto de sistema onde se gerencia frameworks, competências,
templates e **vínculos a cursos e atividades** em **modais, sem sair da visão de sistema** — espelhando a forma
como o `format_mtube` gerencia grupos/agrupamentos/inscrições.

---

## 2. Catálogo de padrões reutilizáveis (aprendidos do format_mtube)

### 2.1 Os webservices nativos, no contexto real de uso

Os WS abaixo **raramente são chamados "na mão"**: eles são os endpoints AJAX por trás dos módulos JS de alto
nível do core. Construir uma admin fluída = usar os módulos JS e escrever apenas as classes PHP de aba, os
`dynamic_form` e suas external functions.

| Webservice (endpoint AJAX) | Módulo/JS do core | Uso concreto no mtube |
|---|---|---|
| `core_dynamic_tabs_get_content` | `core/dynamic_tabs` → `core/local/repository/dynamic_tabs::getContent` | Carrega/recarrega o HTML de cada aba do modal de Participantes sem reload. Recebe o **nome da classe PHP** da aba + args JSON; devolve HTML já com `js_call_amd`. Refresh pós-ação em `amd/src/features/dynamic_tab_helpers.js`. |
| `core_form_dynamic_form` | `core_form/modalform` (back-end de `\core_form\dynamic_form`) | get/validate/submit AJAX de form em modal: `group_icon_form`, `database_fields_form`, `addvideo_form`. |
| `core_output_load_template_with_dependencies` | `core/templates` (`Templates.render`) | Renderiza mustache no cliente (corpos de modal, resultados de lote, cartão de participante) com deps de JS/strings. |
| `core_get_string` / `core_get_strings` | `core/str` (`getString`) | i18n no cliente em todos os módulos AMD. |
| `core_get_fragment` | `core/fragment` (`Fragment.loadFragment`) | Injeta fragmento HTML server-side no modal — ex.: form nativo `enrol_manual:enrol_users_form` dentro do modal de inscrições (`features/manual_enrol.js`). |
| `tiny_autosave_resume_session` | TinyMCE (`tiny_autosave`) | Restaura rascunho quando um campo `editor` vive num `dynamic_form` em modal. É efeito colateral, não chamada explícita. |
| `format_mtube_manage_groups` (próprio) | `core/ajax` (`Ajax.call`) | CRUD de grupos/agrupamentos; retorna `{success, id}` e dispara refresh da aba. |

### 2.2 Padrão 1 — Aba dinâmica: `\core\output\dynamic_tabs\base`

- **Core:** `lib/classes/output/dynamic_tabs/base.php`.
- **Referência mtube:** `course/format/mtube/classes/output/dynamictabs/participants/groups.php`
  (estende `participant_list.php`).
- A classe implementa `export_for_template()`, `get_template()`, `get_tab_label()`, `is_available()`.
- **Detalhe-chave:** dentro de `export_for_template()` ela chama
  `$PAGE->requires->js_call_amd('format_mtube/features/groups', 'init')`. Assim, **toda vez que a aba é
  recarregada via `getContent`, o JS é re-anexado** ao novo HTML. Esse é o coração do "command center" sem reload.

```php
// course/format/mtube/classes/output/dynamictabs/participants/groups.php (resumo)
class groups extends participant_list {
    public function export_for_template(\renderer_base $output) {
        // ... monta $groups, $groupings, permissões ...
        $PAGE->requires->js_call_amd('format_mtube/features/groups', 'init');
        return (object) [ /* dados do template */ ];
    }
    public function get_tab_label(): string { return get_string('groups', 'core_group'); }
    public function is_available(): bool { /* checa capability */ }
    public function get_template(): string { return 'format_mtube/participants/groups'; }
}
```

### 2.3 Padrão 2 — Formulário em modal: `\core_form\dynamic_form` + `core_form/modalform`

- **Core:** `lib/form/classes/dynamic_form.php`; JS `lib/form/amd/src/modalform.js` (`core_form/modalform`).
- **Referência mtube mínima e limpa:** `course/format/mtube/classes/form/group_icon_form.php`.
- Métodos a implementar: `get_context_for_dynamic_submission`, `check_access_for_dynamic_submission`,
  `definition`, `set_data_for_dynamic_submission`, `process_dynamic_submission` (e opcionalmente
  `definition_after_data`, `get_page_url_for_dynamic_submission`).

```javascript
// abre o form em modal sem reload (padrão mtube em features/groups.js)
const form = new ModalForm({
    formClass: 'format_mtube\\form\\group_icon_form',
    args: {courseid, groupid},
    modalConfig: {title: await getString('changepicture', 'group')},
});
form.addEventListener(form.events.FORM_SUBMITTED, () => refreshDynamicTab(...));
form.show();
```

### 2.4 Padrão 3 — External function própria + re-render da aba

`db/services.php` (`ajax => true` + `capabilities`) → `classes/external/*.php` (valida contexto +
`require_capability`) → JS chama via `Ajax.call` → no sucesso, recarrega a aba via `getContent`. Padrões extras
do mtube que valem registrar: **processamento em chunks** para lote (`import_group_members.php`, `CHUNK_SIZE=200`)
e **subtabs com estado em `sessionStorage`** (`dynamic_tab_helpers.js`).

> Esses três padrões + `core/fragment` (reaproveitar forms nativos) + `core/templates`/`core/str` são o kit
> completo. Tudo já existe no core 5.1 (verificado nesta análise).

---

## 3. O que o Dimensions já tem (reaproveitar integralmente)

A base de back-end **não precisa de reescrita** para virar admin modal:

- **Handlers `core_customfield`:** `classes/customfield/competency_handler.php`, `classes/customfield/lp_handler.php`.
- **CRUD ajax pronto** (`db/services.php` + `classes/external/`), todos `ajax => true` e capability-checked,
  retornando o registro completo já com customfields:
  `local_dimensions_create_competency`, `_update_competency`, `_read_competency`,
  `local_dimensions_create_template`, `_update_template`, `_read_template`,
  e leituras auxiliares `_get_competency_courses`, `_get_competency_rule_data`, `_get_course_progress`,
  `_get_courses_completion_status`, `_get_user_competency_summary_in_plan`, `_get_fontawesome_icons`.
- **Trait `customfields_io`** (`classes/external/customfields_io.php`): round-trip de customfields
  (text/select/textarea; picture é pulado por exigir draft-area). Reutilizável na admin modal **e** no plugin
  companheiro (ver `companion-modfields-proposal.md`).
- Observers/caches de invalidação, datasources de reportbuilder, `picture_manager`.

**Conclusão:** o gap é quase 100% **front-end + camada de formulário**.

---

## 4. O gap

- Forms são `moodleform` (`classes/form/competency_form.php`, `classes/form/template_form.php`) renderizados em
  página dedicada → precisam de variantes `dynamic_form` (ou wrapper) para abrir em modal.
- **Zero** uso de `core/modal*`, `core_form/modalform`, `core/fragment`, `core/dynamic_tabs` nos 14 módulos AMD
  atuais — todos apenas enriquecem páginas com recarga.
- Estado de UI (framework selecionado, busca, view tree/table) vive na **URL** — numa admin sem reload precisa
  migrar para estado client-side.
- **Sem vínculo competência↔atividade.** O core 5.1 já provê a base:
  `competency/classes/course_module_competency.php` + `core_competency\api` (tabela `competency_modulecomp`).

---

## 5. Design proposto — "Central de Competências"

Página/modal acessível em contexto de sistema, montada com `core/dynamic_tabs`. Abas:

| Aba | Conteúdo | Classe PHP (nova) | Template |
|---|---|---|---|
| **Frameworks** | Lista + CRUD de frameworks | `local_dimensions\output\dynamictabs\frameworks` | `local_dimensions/admin/frameworks` |
| **Competências** | Árvore hierárquica + CRUD | `local_dimensions\output\dynamictabs\competencies` | `local_dimensions/admin/competencies` |
| **Templates/Planos** | Cards + CRUD | `local_dimensions\output\dynamictabs\templates` | `local_dimensions/admin/templates` |
| **Vínculos** | Vincular competência a cursos **e atividades** | `local_dimensions\output\dynamictabs\links` | `local_dimensions/admin/links` |

Cada aba estende `\core\output\dynamic_tabs\base` (espelhando `groups.php` do mtube) e re-anexa seu JS em
`export_for_template()`. Cada CRUD é um `\core_form\dynamic_form`
(`local_dimensions\form\competency_dynamic_form`, `template_dynamic_form`, `framework_dynamic_form`) aberto por
`core_form/modalform`, **reutilizando as external functions existentes e a trait `customfields_io`** dentro de
`process_dynamic_submission`. Pós-ação → refresh da aba via `getContent`.

### 5.1 Vínculo a atividades (novo)

Novas external functions finíssimas sobre a API core de competência de módulo:

- `local_dimensions_link_competency_to_module` → `core_competency\api::add_competency_to_course_module($cmorid, $competencyid)` (competency/classes/api.php:1453)
- `local_dimensions_unlink_competency_from_module` → `api::remove_competency_from_course_module($cmorid, $competencyid)` (api.php:1496)
- `local_dimensions_list_module_competencies($cmid)` → `api::list_course_module_competencies($cmorid)` (api.php:1241) / `course_module_competency`

**UI da aba "Vínculos":** escolher curso → carregar atividades (via `get_fast_modinfo` num fragment ou external
function de leitura) → marcar competências por atividade num picker em modal. Tudo sem ir ao curso.

---

## 6. Riscos / pontos de atenção

1. **Árvore de competências e regras** — hoje a árvore usa `tool_lp/tree` e a config de regras usa
   `tool_lp/competencyruleconfig`, feitos para página. Adaptar a modal exige (a) embutir a árvore num pane do
   modal com cuidado de **re-init a cada refresh**, ou (b) um modelo alternativo (breadcrumb + lista plana).
   **Recomendação:** começar pela aba mais simples (**Templates**) como prova de conceito antes de migrar
   Competências.
2. **Upload de imagem em customfield (picture)** — `customfields_io` pula picture (precisa draft-area). Em modal,
   `dynamic_form` lida com `filepicker` normalmente; ver `group_icon_form` (faz `file_prepare_draft_area` em
   `set_data_for_dynamic_submission` e processa em `process_dynamic_submission`). Bom caminho para reincluir
   picture na admin modal.
3. **Estado de UI sem reload** — migrar framework/busca/view da URL para estado client-side (o mtube usa
   `sessionStorage` para as subtabs em `dynamic_tab_helpers.js`).
4. **Capabilities/contexto** — manter `moodle/competency:competencymanage` / `:templatemanage` nas external
   functions (já existem) e validar contexto (sistema ou categoria de curso) como hoje.

---

## 7. Roadmap sugerido de migração (incremental)

1. **Fase 0 — Andaime:** página host da "Central" + `core/dynamic_tabs` com uma única aba.
2. **Fase 1 — Templates (POC):** aba Templates em `dynamic_tabs` + `template_dynamic_form` em modal reusando
   `create/update/read_template`. Valida o padrão ponta-a-ponta.
3. **Fase 2 — Frameworks** e **Vínculos a cursos** (já há `get_competency_courses`).
4. **Fase 3 — Vínculo a atividades:** novas external functions sobre `core_competency` + picker modal.
5. **Fase 4 — Competências (árvore):** a parte mais complexa; decidir entre adaptar `tool_lp/tree` ou modelo
   alternativo.

---

## 8. Referências de arquivos

**Core (5.1, verificado):**
`lib/classes/output/dynamic_tabs/base.php`, `lib/form/classes/dynamic_form.php`,
`lib/form/amd/src/modalform.js`, `competency/classes/course_module_competency.php`, `competency/classes/api.php`.

**mtube (modelo):**
`course/format/mtube/classes/output/dynamictabs/participants/groups.php`,
`course/format/mtube/classes/form/group_icon_form.php`,
`course/format/mtube/classes/external/manage_groups.php`,
`course/format/mtube/amd/src/features/{fab,groups,dynamic_tab_helpers,manual_enrol}.js`,
`course/format/mtube/db/services.php`.

**Dimensions (a reaproveitar):**
`local/dimensions/classes/external/{create,update,read}_{competency,template}.php`,
`local/dimensions/classes/external/customfields_io.php`,
`local/dimensions/classes/customfield/{competency_handler,lp_handler}.php`,
`local/dimensions/classes/form/{competency_form,template_form}.php`,
`local/dimensions/db/services.php`.

---

## 9. Refinamentos da prototipação (debate no Miro + mockups) — 2026-06-26

Fluxos debatidos no board Miro (`uXjVGkGPWOM`): Fluxo A (pilares/dependência), Fluxo B (jornada
guiada por gates), ERD `core_competency`, e doc de decisões. Mockups inline validados:
baseline (manage atual), Central/Estrutura, modal de vínculo, Planos + modal de coorte, e os
modais de editar competência e editar plano.

### 9.1 IA validada
- **Contexto persistente** (Sistema / Categoria de curso) no topo, governando frameworks **e** planos.
  Contador **adaptativo**: frameworks por categoria em *Estrutura*; planos por categoria em *Planos*.
  Categoria exige selecionar uma categoria (estado guiado; reaproveita `frameworkcount`/`hasframeworks`).
- **Trilha hierárquica adaptativa ao modo** (não fixa):
  - *Estrutura*: `Contexto ▸ Framework ▸ Competência` — Framework é switcher; o segmento Competência
    reflete a seleção na árvore (clique foca/realça).
  - *Planos*: `Contexto ▸ Plano`.
- **Planos são cross-framework**: não se filtra plano por framework; **busca por competência**; dentro
  do plano, **picker cross-framework** com tag do framework de origem. No detalhe da competência, cross-ref
  "aparece em N planos".
- **Vínculos = ações em modal**: dentro de Competência (→ cursos **e** atividades, com **`ruleoutcome`**
  por vínculo: não fazer nada / anexar evidência / enviar para revisão / concluir a competência) e dentro
  de Plano (→ competências). **Atribuir plano a usuários/coortes + sincronização de coorte** no estilo da
  gestão de grupos do mtube (`template_cohort`). **Sem "colar lista".**

### 9.2 Edição em modal (dynamic_form) — fiel aos forms atuais
`competency_form` e `template_form` viram `dynamic_form` em modal, preservando as **seções com
título + descrição (explicações)** e todas as funcionalidades:
- **Competência**: Informações básicas (framework, pai + taxonomia, nome\*, idnumber\*, descrição) ·
  Avaliação (escala + *configurar escala* — `tool_lp/scaleconfig`) · **Regra de competência**
  (resumo + *configurar* **só quando há filhas** — `tool_lp/competencyruleconfig`) · Campos personalizados.
- **Plano**: Informações básicas (categoria, nome\*, descrição) · Publicação (visível, data de conclusão) ·
  Campos personalizados (a área `lp` acrescenta os 3 específicos abaixo).

### 9.3 Catálogo real de campos personalizados (`helper.php` / `constants.php`)
Provisionados no install via `helper::ensure_custom_fields_exist()`. Renderizados pelo handler da área
(`competency_handler` / `lp_handler`) e gravados via `customfields_io` / `instance_form_save`.

| Campo (shortname) | Tipo | Área | Condicional |
|---|---|---|---|
| Modo de exibição (`displaymode`) | select | lp | — |
| Fonte da sublinha (`subline_source`) | select | lp | — |
| Identificador do template (`template_idnumber`) | text | lp | — |
| Filtro de inscrição (`enrollmentfilter`) | select | lp + competency | — |
| Redirecionar curso único (`singlecourseredirect`) | select | lp + competency | — |
| Cor de fundo (`custombgcolor`) | text → **colorpicker** | lp + competency | — |
| Cor do texto (`customtextcolor`) | text → **colorpicker** | lp + competency | — |
| Imagem do card (`customcard`) | picture | lp + competency | só **fora** do builtin mode |
| Imagem de fundo (`custombgimage`) | picture | lp + competency | só **fora** do builtin mode |
| Ano (`tag1`) | select | lp + competency | — |
| Categoria (`tag2`) | select | lp + competency | — |
| Tipo (`type`) | select | lp + competency | — |
| SCSS personalizado (`customscss`) | textarea | lp + competency | só se `enablecustomscss` |

### 9.4 Anexo de imagens — padrão Moodle (arrasta e solta) em modal
Usar o `filemanager`/`filepicker` nativo com **área de rascunho** (`file_prepare_draft_area`) e
**drag & drop** — funciona dentro de `dynamic_form`/`core_form/modalform` (provado pelo
`format_mtube\form\group_icon_form`). Em **builtin mode**, `customcard`/`custombgimage` não existem como
customfield: o `picture_manager` gerencia as imagens direto — a UI desses campos é condicional ao modo.

### 9.5 Performance e escala (regra de projeto — uso intensivo, dezenas de milhares)
| Tema | Regra |
|---|---|
| Listas / pickers / coortes | **Paginação server-side** sempre (`limitfrom`/`limitnum` + total). |
| Busca | **AJAX debounced** → external function; nunca carregar tudo no cliente. |
| Árvore de competências | **Lazy-load** (filhos ao expandir) + **busca server-side** (framework pode ter centenas). |
| Web services | Enxutos, **capability-checked**, payload mínimo, **sem N+1** (`get_fast_modinfo`, queries em lote), reaproveitando os **MUC caches** existentes. |
| Reuso | External functions + `customfields_io` + handlers atuais; vínculo a atividade via `core_competency\api::add_competency_to_course_module`. |

### 9.6 Princípio: ater ao que já existe
O redesign **reorganiza** funcionalidades existentes numa superfície única com modais — **sem features
novas**. O único acréscimo real é **expor** o vínculo competência↔atividade, que já existe no core
(`course_module_competency`) mas hoje não é exposto pelo Dimensions.
