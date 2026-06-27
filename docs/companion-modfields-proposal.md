# Proposta — plugin companheiro de campos customizados de atividades (`local_modfields`)

> **Status:** Proposta de arquitetura (plugin ainda não existe).
> **Nome de trabalho:** `local_modfields` (definitivo a confirmar).
> **Posicionamento:** plugin local **standalone**, útil sozinho, com **integração opcional** ao
> `local_dimensions`. Companheiro pensado para **filtro/agrupamento de atividades por campo** (ex.: por
> competência).
> **Local deste doc:** mora em `local/dimensions/docs/` por ser companheiro; mover para o repo do novo plugin
> quando ele nascer.

---

## 1. Problema e intenção

Hoje, para anexar metadados a atividades (módulos de curso) e usá-los para filtrar/agrupar, as opções são:

- **`format_mtube` (section type `database`):** UX excelente (modal de definição + injeção no form da atividade
  + filtro/sort/busca reativos), mas **storage próprio e limitado** — até 10 campos (`meta1..meta10`) por
  **seção**, gravados em tabela própria `format_mtube_cmprop`; **não usa** `core_customfield`.
- **`local_modcustomfields` (Adapta / Daniel Neis):** arquitetura **correta** (API nativa `core_customfield`,
  defs em contexto de sistema, valores em contexto de módulo via `itemid = cmid`, ganchos padrão de `mod_form`),
  mas **UX padrão**, sem fluidez.

A intenção é o melhor dos dois mundos: **API nativa `core_customfield` + shared custom fields (Moodle 5.1,
MDL-86065)** com UX mais intuitiva — entregando primeiro um **MVP nativo** e a **UX estilo mtube na fase 2**.

---

## 2. Comparativo

| Aspecto | mtube (`database` section) | Adapta `local_modcustomfields` | **Proposta `local_modfields`** |
|---|---|---|---|
| Storage | Tabela própria `format_mtube_cmprop` (`meta1..meta10`) + defs JSON em `course_sections.sectionfields` | **Nativo** `core_customfield` (`customfield_data`) | **Nativo** `core_customfield` |
| Limite de campos | 10 por seção | Ilimitado | Ilimitado |
| Contexto das definições | Seção (curso) | **Sistema** | **Sistema** (+ categorias **compartilhadas** 5.1) |
| Contexto dos valores | Módulo (por cm) | Módulo (`itemid = cmid`) | Módulo (`itemid = cmid`) |
| Edição dos valores | Form injetado no cm + modal de defs | Injetado no `mod_form` | Injetado no `mod_form` (MVP); inline em modal (fase 2) |
| UX de definição | Modal `dynamic_form` (fluída) | Página admin nativa | Página admin nativa (MVP) → modal/dynamic_tabs (fase 2) |
| Tipos de campo | Fixos (single/multiple/text/...) | Extensível (`customfield_*`) | Extensível (`customfield_*`) |
| Filtro/agrupamento | WS `format_mtube_get_database_cms` (reativo) | Não (só reportbuilder) | WS de leitura + entidade reportbuilder |
| Backup/restore, privacy, locking, visibility | Manual/parcial | Nativo | Nativo |

**Por que nativo + shared vence o storage bespoke do mtube:** extensibilidade de tipos (plugins `customfield_*`),
categorias/agrupamento, locking/visibilidade, backup/restore e privacy "de graça", reportbuilder e — crucial —
**compartilhamento entre componentes**: uma **categoria compartilhada "Competência"** pode ser reusada em
**atividades** e nas **competências** do Dimensions.

---

## 3. Arquitetura do MVP (nativo)

### 3.1 Handler de área de módulo

`local_modfields\customfield\mod_handler extends \core_customfield\handler`
(em `classes/customfield/mod_handler.php`):

- `get_configuration_context()` → `context_system::instance()`.
- `get_instance_context(int $cmid = 0)` → `context_module::instance($cmid)` (ou system quando `0`).
- `get_configuration_url()` → `new moodle_url('/local/modfields/customfield.php')`.
- `can_configure()` → `has_capability('moodle/course:configurecustomfields', context_system::instance())`.
- `can_edit($field, $cmid)` / `can_view($field, $cmid)` → espelhar o handler de curso do core
  (locked → `moodle/course:changelockedcustomfields`; visibility → teachers/all).
- `config_form_definition()` → opções de locked + visibility (como `local_modcustomfields`).
- `restore_instance_data_from_backup()` → restauração no backup/restore de atividade.

**Referências para espelhar:** `local_modcustomfields\customfield\mod_handler`,
`core_course\customfield\course_handler`, e o `local_dimensions\customfield\competency_handler` (mesmo
codebase).

### 3.2 Página de configuração

`local/modfields/customfield.php` usando `\core_customfield\output\management` (a UI nativa de
categorias/campos). **Shared categories vêm praticamente de graça:** o handler base
(`customfield/classes/handler.php`, ~linha 541) já consulta `api::is_shared_category_enabled(...)`. Na
implementação, **confirmar o opt-in exato** para a área aceitar categorias compartilhadas — ver
`customfield/classes/shared.php`, `customfield/classes/customfield/shared_handler.php` e
`customfield/classes/external/toggle_shared_category.php`.

### 3.3 Ganchos no `lib.php` (mesmo trio do Adapta)

- `local_modfields_coursemodule_standard_elements($formwrapper, $mform)` →
  `mod_handler::create()->instance_form_definition($mform, $cmid)` (filtrado por tipo de módulo habilitado e
  visibilidade).
- `local_modfields_coursemodule_validation($formwrapper, $data)` → `instance_form_validation(...)`.
- `local_modfields_coursemodule_edit_post_actions($moduleinfo, $course)` → mapear
  `moduleinfo->coursemodule` → `->id` e `instance_form_save($moduleinfo, ...)`.

### 3.4 Outros

- **Settings** (`settings.php`): por quais tipos de módulo o plugin é habilitado (`is_available_module`).
- **Privacy provider** (valores em `customfield_data`).
- **Lang** `en` + `pt_br`.
- **`version.php`:** `requires` ≥ Moodle 5.1 se for usar shared fields; ou ≥ 3.7 com shared como melhoria
  opcional condicionada à versão.

---

## 4. Valor companheiro do Dimensions (filtro/agrupamento)

- Valores ficam em `customfield_data` (`itemid = cmid`, contexto de módulo).
- **Exposição para a interface do curso:** external function ajax
  `local_modfields_get_module_field_data(courseid)` espelhando a lógica de filtro de
  `format_mtube\external\get_database_cms` (busca/sort/filtro por valores de campo), para alimentar uma UI
  reativa de filtro/agrupamento de atividades.
- **Reportbuilder:** entidade/datasource para agrupar atividades por valor de campo.
- **Ponte com Dimensions (opcional, sem dependência rígida):** uma **categoria compartilhada "Competência"**
  reusada pela área de competência do Dimensions **e** pela área de módulo deste plugin → atividades "marcadas"
  com a mesma competência podem ser filtradas/agrupadas junto da visão de competências. A ponte só ativa se o
  `local_dimensions` estiver presente.

> Observação sobre shared fields: o compartilhamento do MDL-86065 é de **definições de categoria/campo** entre
> componentes — não de valores. O elo "competência ↔ atividade" no nível de **dado** continua sendo o valor do
> campo por atividade (ou o vínculo `competency_modulecomp` proposto no redesign da admin). A categoria
> compartilhada garante **vocabulário e definição únicos** em ambos os lados.

---

## 5. Fase 2 — UX estilo mtube

Augmentar a página nativa e a edição via `mod_form` com os padrões de
[`admin-redesign.md`](admin-redesign.md) (seção 2):

- **"Command center" de campos** em modal/`dynamic_tabs` para gerir categorias/campos com hierarquia.
- **Edição inline** do valor de um campo numa atividade via `\core_form\dynamic_form` sobre o instance form do
  handler (sem abrir o `mod_form` inteiro), espelhando `group_icon_form` + `core_form/modalform`.
- Filtro/sort/busca reativos na visão do curso, espelhando `database.js` do mtube.

---

## 6. Esqueleto de arquivos do plugin (futuro)

```
local/modfields/
├── version.php
├── settings.php                         # tipos de módulo habilitados
├── customfield.php                      # página de management (core_customfield\output\management)
├── lib.php                              # 3 ganchos coursemodule_* + is_available_module
├── db/
│   ├── access.php                       # (reusa moodle/course:configurecustomfields)
│   └── services.php                     # local_modfields_get_module_field_data (fase companheira)
├── classes/
│   ├── customfield/mod_handler.php      # extends \core_customfield\handler
│   ├── external/get_module_field_data.php
│   ├── reportbuilder/local/entities/... # agrupamento por campo
│   └── privacy/provider.php
└── lang/{en,pt_br}/local_modfields.php
```

---

## 7. Riscos / a confirmar na implementação

1. **Opt-in de shared categories** para a área de módulo (API exata da 5.1) — validar com
   `customfield/classes/shared.php` / `shared_handler.php`.
2. **Filtro performático na visão do curso** — valores em `customfield_data` exigem join; avaliar cache (o mtube
   cacheia por seção). Para muitos cm/curso, considerar pré-carga + filtro client-side como o mtube faz.
3. **Backup/restore e duplicação de atividade** — garantir `restore_instance_data_from_backup` e cópia de valores.
4. **Capability** — reusar `moodle/course:configurecustomfields` é coerente com o core e com o Adapta.

---

## 8. Referências

**Core 5.1 (verificado):** `customfield/classes/handler.php`, `customfield/classes/shared.php`,
`customfield/classes/customfield/shared_handler.php`, `customfield/classes/api.php`
(`is_shared_category_enabled`), `customfield/classes/external/toggle_shared_category.php`,
`customfield/classes/output/management.php`.

**Modelos:** `format_mtube` (UX: `amd/src/database.js`, `classes/form/database_fields_form.php`,
`classes/external/get_database_cms.php`, `classes/local/database/fields.php`, tabela `format_mtube_cmprop`);
`local_modcustomfields` da Adapta (API nativa: handler `mod_handler` + ganchos `coursemodule_*` em `lib.php`);
`local_dimensions` (`classes/customfield/competency_handler.php`, `classes/external/customfields_io.php`).

**Tracker:** MDL-86065 — "Create mechanism for defining and re-using shared custom field categories" (fix
version 5.1).
