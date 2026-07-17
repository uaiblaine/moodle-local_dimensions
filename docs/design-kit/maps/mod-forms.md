# Mapa de Campos — os quatro corpos de `dynamic_form` (as-is)

O kit mapeia a **casca** de todo modal (`modal-shell.html`) mas nunca mapeou nenhum **corpo** de
`core_form\dynamic_form` — a lacuna que este arquivo fecha (registrada em `mod-scale.md:169` e no
README). Um `ls classes/form/` devolve **quatro**, todos abertos como `core_form/modalform` (sem
reload de página):

| Form | Abre a partir de | Cria/edita | Casca de shell |
| --- | --- | --- | --- |
| `framework_dynamic_form.php` | `frameworks.js` (aba Estruturas) | `competency_framework` | `modal-shell.html` + o link "Abrir escalas" |
| `competency_dynamic_form.php` | `structure.js` (aba Estruturas) | `competency` | `modal-shell.html` |
| `template_dynamic_form.php` | `plans.js` (aba Planos) | `competency_template` | `modal-shell.html` |
| `import_framework_dynamic_form.php` | `frameworks.js` (aba Estruturas) | importa CSV | `modal-shell.html` |

Convenção de ID aqui: `FORM-FWK-*`, `FORM-COMP-*`, `FORM-TPL-*`, `FORM-IMP-*`. **Migração:** as IDs
`MOD.SCALE-ACTION/-SUMMARY/-HIDDEN` eram **provisórias** no `mod-scale.md` (o gatilho da escala mora
no corpo do framework form, não no modal de escala); passam a viver aqui como `FORM-FWK-SCALE-*`.

## Fundações compartilhadas (as quatro)

- **Casca é o `core_form/modalform`.** Cada opener faz `new ModalForm({formClass, args, modalConfig:{title}})`.
  Título é `getString(<key>, <comp>)` no opener, não no form.
- **ids são randomizados.** O `dynamic_form` sufixa **todo** id de elemento (`id_scaleid` →
  `id_scaleid_c5fLCIS8…`), então **todo JS que fala com um campo o seleciona por `name`**, nunca por
  `#id_<name>` (ver [[moodle-hub-ui-gotchas]]). Onde um id **precisa** ser fixo (o `tool_lp/scaleconfig`
  do core casa por seletor), o form o **pina** explicitamente — `id_scaleconfiguration`,
  `id_scaleid_central`, `tool_lp_scaleconfiguration_central`, `id_scaleconfigbutton_central`.
- **`js_call_amd` mora no `definition_after_data()`, nunca no `definition()`.** O `definition()` roda
  no construtor do moodleform, **antes** do `start_collecting_javascript_requirements()` do modalform;
  um `js_call_amd` ali nunca chega ao modal. Vale para o painel de contraste, o swatch e o pin de SCSS
  do competency/template (ver [[moodle-hub-ui-gotchas]], os 2 traps do dynamic_form).
- **Editor de descrição é mídia-por-URL-só.** Os três forms com `description` usam
  `{maxfiles:1, return_types:FILE_EXTERNAL, enable_filemanagement:false}` — o `maxfiles:1` é o
  contorno do crash do `tiny_media` (embed sem fpoptions) no 5.0–5.2, e o `FILE_EXTERNAL` tira o
  repositório do picker: imagem só por URL, sem área de arquivo (ver [[dimensions-tinymce-media-crash]]).
- **Duas áreas de customfield, e só duas.** `competency_handler` (área competência) e `lp_handler`
  (área template) injetam o bloco de customfields via `instance_form_definition()`. **O framework
  form injeta zero** (frameworks não são área de customfield); **o import** também zero (os customfields
  viajam como colunas `cf_*` do CSV, aplicadas pelo importer). Só **competency** e **template** têm o bloco.

---

## `FORM-FWK` — corpo do form de estrutura (`framework_dynamic_form.php`)

Abre em dois caminhos, ambos por `frameworks.js`: **editar** (`editFramework`, `:194`, args `{id}`,
título `central_frameworks_edit`, do sticky-footer) e **criar** (`createFramework`, `:203-204`, args
`{id:0, contextid}`, título `central_frameworks_new`, do botão da toolbar). **Salvar** → toast
`central_frameworks_saved` + `reloadPane` (`frameworks.js:179-182`); a aba não tem caminho in-place.
Gate: `moodle/competency:competencymanage` no contexto de submissão (`form.php:115-117`). **Sem customfields.**

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `FORM-FWK-ID` | `[hidden]` | hidden | `form.php:137-138` | `PARAM_INT` | id da estrutura; 0 = criar. Dirige o branch criar-vs-atualizar (`:247,263`) |
| `FORM-FWK-CONTEXTID` | `[hidden]` | hidden | `form.php:139-141` | `PARAM_INT` | semeado no criar do `region.dataset.contextid` (`frameworks.js:204`); escopa a checagem de shortname único. **Não** relido no editar |
| `FORM-FWK-SHORTNAME` | Short name | text | `form.php:143-146` | `PARAM_TEXT` · maxlength 100 | rótulo **nativo** `tool_lp`. `required` só client; unicidade é server (ver validação) |
| `FORM-FWK-IDNUMBER` | ID number | text | `form.php:148-151` | `PARAM_RAW` · maxlength 100 | `RAW` de propósito (idnumber aceita chars arbitrários). Sem checagem de unicidade neste form |
| `FORM-FWK-DESC` | Description | editor | `form.php:157-164` | `PARAM_CLEANHTML` · rows 4 | mídia-por-URL-só (fundação acima). `set_data` `{text,format}` (`:224-227`) |
| `FORM-FWK-SCALE` | Scale | select | `form.php:166-184` | `PARAM_INT` | **select congelável** (ver Controles). Rótulo `central_frameworks_scale`; options `get_scales_menu()` (core). `required` só quando não-congelado. É onde o erro de escala-incompleta é ancorado (`:295`), embora o portador seja o hidden |
| `FORM-FWK-SCALE-HIDDEN` | `[hidden]` | hidden | `form.php:186-187` | `name="scaleconfiguration"` · `PARAM_RAW` · id fixo `id_scaleconfiguration` | **o destino real da escala** (migra do `MOD.SCALE-HIDDEN`). Escrito por JS (`frameworks.js:76`), zerado na troca de escala (`:117`). Persistido verbatim (`:261`) |
| `FORM-FWK-SCALE-ACTION` | Configurar escala | static (botão+resumo) | `form.php:189-195` | `data-action="configure-scale"` · `data-region="scaleconfig-summary"` | **o gatilho do `MOD.SCALE`** (migra do `MOD.SCALE-ACTION`). String hand-built: botão `.btn.btn-secondary.btn-sm` + span de resumo. Fiado por **delegação document-level capture-phase** (`frameworks.js:95-123`, uma vez por página) porque o corpo do form vive num modalform cujo ciclo nunca roda o init da aba. Clique → `openScaleConfigForForm` (`:62-86`) abre o `MOD.SCALE`. O resumo mostra `central_frameworks_scaleconfigured`="Configurada" só quando o config gravado já está completo (`:189-190`) |
| `FORM-FWK-VISIBLE` | Visible | selectyesno | `form.php:197-198` | default 1 | flag do próprio framework — **distinto** do `FWK-ROW-VIS` (o toggle do sticky-footer que a vira por WS sem abrir o form) |
| `FORM-FWK-TAXONOMY` | Level {i} | select (loop) | `form.php:200-204` | — | `taxonomies[1..N]`, N=`max(depth,4)`; options `get_taxonomies_list()` (core). Persistido como CSV (`:255-256`). **Gotcha de load:** o getter mágico do persistente explode a coluna CSV num array indexado de 1 — `(string)` nele = warning que o debug developer escala a exceção (`:232-234`) |

**Validação (`form.php:281-299`) — as duas bloqueiam:** (1) **shortname único** no mesmo `contextid`
→ `shortnametaken` (`:287-292`); (2) **escala incompleta** → `central_frameworks_scaleincomplete`
ancorado no `scaleid` (`:294-296`), via `helper::scaleconfig_is_complete` (exige ≥1 default **e** ≥1
proficiente), o mesmo que o modal filho exige antes de resolver — **bloqueia dos dois lados**. Não
re-checa required de shortname/idnumber/scaleid (client-only) nem unicidade de idnumber.

**Controles de design:** (a) **`FORM-FWK-SCALE` congelado** — quando `framework->has_user_competencies()`,
o select vira `readonly`+`disabled` + `setConstant` + **sem** rule `required` (`:173-184`): o disabled
sai do POST mas o constant abastece o `get_data()` e o JS ainda lê `.value` (a receita de congelar
select, [[moodle-hub-ui-gotchas]]); a troca de escala **não** zera o config quando congelado. Duas
telas visuais (dropdown editável × cadeado), e o valor cinza não pode ler como vazio. (b) **Gatilho
`MOD.SCALE`** (migrado, acima). (c) **Link "Abrir escalas" no cabeçalho** — injetado por
`injectScalesLink` (`frameworks.js:133-163`) no `LOADED`, à esquerda do fechar, **só** quando
`canscalespage==='1'`; a classe `.local-dimensions-headerlink-modal` entra no diálogo sempre (contrato
do restyle do fechar). (d) Descrição URL-só.

---

## `FORM-COMP` — corpo do form de competência (`competency_dynamic_form.php`)

Abre por `structure.js` em três sítios: **editar** (`:1253-1258`, título `editcompetency`), **adicionar
filha** (`:1259-1260`) e o botão "Adicionar competência" do cabeçalho (`:1425-1428`), os dois últimos
título `addcompetency` (**nativos** `tool_lp`). **Salvar** (`structure.js:804-810`): se editou →
`refreshNode` **in-place** (mantém expansão+seleção); se mudou o pai → `reloadPane`+`revealNode` no
novo lugar; se criou → `reloadPane`. **Sem toast** — a confirmação é o flash/re-render in-place. Gate:
`moodle/competency:competencymanage` no contexto da estrutura (`form.php:112-114`).

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `FORM-COMP-ID` | `[hidden]` | hidden | `form.php:131-132` | `PARAM_INT` | 0 = criar |
| `FORM-COMP-FWKID` | `[hidden]` | hidden | `form.php:133-135` | `PARAM_INT` | escolhe o contexto de submissão + escopa o pai e a unicidade de idnumber |
| `FORM-COMP-PARENT` | Parent competency | select | `form.php:136-142` | `PARAM_INT` | rótulo **nativo**. Options `get_parent_options` (`:79-97`): raiz + toda competência da estrutura **menos** a editada e suas descendentes (não pode virar filha de si). **Desacoplado no editar:** o pai submetido **não** vai ao `update_competency` — só um `set_parent_competency` separado (`:303-305`) reparenta, e client-side isso força `reloadPane`+`revealNode` |
| `FORM-COMP-SHORTNAME` | Short name | text | `form.php:144-147` | `PARAM_TEXT` · maxlength 100 | rótulo core. `required` só client |
| `FORM-COMP-IDNUMBER` | ID number | text | `form.php:149-151` | `PARAM_RAW` | **único server** (ver validação → `idnumberexists`); é o único campo com validador bloqueante |
| `FORM-COMP-DESC` | Description | editor | `form.php:157-164` | `PARAM_CLEANHTML` | mídia-por-URL-só (fundação) |
| `FORM-COMP-SCALE` | Scale | select | `form.php:169-172` | `PARAM_INT` · id fixo `id_scaleid_central` | half do trio de escala inline. Options `[null=>inheritfromframework] + get_scales_menu()`; null = herda. `addHelpButton` |
| `FORM-COMP-SCALE-HIDDEN` | `[hidden]` | hidden | `form.php:174-175` | `PARAM_RAW` · id fixo `tool_lp_scaleconfiguration_central` | destino do diálogo de escala **nativo** (`tool_lp/scaleconfig`) — **mecanismo distinto** do `MOD.SCALE` do framework (aquele é bespoke, este é o do core, sem span de resumo) |
| `FORM-COMP-SCALE-BTN` | Configure scales | button | `form.php:176-181` | id fixo `id_scaleconfigbutton_central` | gatilho **nativo** do `tool_lp/scaleconfig`; incondicional (existe no criar também) |
| `FORM-COMP-CFIELD` | {headers de categoria do core} | customfield (bloco) | `form.php:184` → `competency_handler:160-176` | `customfield_<shortname>` | o **bloco da área competência** (o handler passa o header vazio, então só as categorias do core rotulam). Membros: `enrollmentfilter`/`singlecourseredirect` (selects de cascata), `custombgcolor`/`customtextcolor` (text hex — o par graduado), `tag1`/`tag2`/`type` (selects), `customscss` (só com `enablecustomscss` + cap `local/dimensions:editcustomscss`), `customcard`/`custombgimage` (picture, só no modo externo). Rows `itemid=0, instanceid=<compid>` |
| `FORM-COMP-IMG` | custombgimage / customcard | filemanager | `form.php:184` → `picture_manager:144-166` | áreas `competency_bgimage`/`_cardimage` | **plugin-custom**, só no **modo built-in** de imagem — mutuamente exclusivo com os customfields `picture`. Dois filemanagers 10MB/1 arquivo |
| `FORM-COMP-CASCADE` | `[sem rótulo]` | static | `form.php:187-201` | — | explicador da cascata competência→template→global, `insertElementBefore` acima do `enrollmentfilter` (fallback no fim se ausente); nomeia os dois selects de cascata |

**Validação (`form.php:328-343`) — bloqueia:** idnumber único na estrutura → `idnumberexists`
(`:331-337`); + `helper::validate_customscss` (compila o SCSS, bloqueia em erro, só com feature on).
**Não** valida o par de cor — o painel **aconselha, não bloqueia**.

**Controles de design (todos fiados no `definition_after_data`, `:217-242`):** (1) **trio de escala
inline** via `tool_lp/scaleconfig` (`:225-229`) — nativo, ids fixos. (2) **Painel de contraste WCAG**
via `local_dimensions/central/contrast` (`:238-241`) sobre o par `custombgcolor`/`customtextcolor`:
computa o ratio real (linearização sRGB → luminância → `(L1+.05)/(L2+.05)`, `contrast.js:82-106`),
pill de veredito (excelente≥7/passa≥4.5/atenção≥3/falha) + badges AA/AAA, e até **dois consertos de
um clique** abaixo do AA — mas **aconselha, nunca toca no salvar** (`contrast.js:22-23`); relayouta os
dois `.fitem` num flex de duas colunas (`:475-491`). (3) **Swatch de cor** (`colour_swatch`, `:232-235`).
(4) **SCSS pinado em `FORMAT_PLAIN`** (`helper::force_customscss_plain`, `:224`).

> **A lacuna real do painel de contraste** (herdada do `pln-plans.md:293-298`, não re-litigada aqui):
> ele gradua **texto × fundo**, mas o cabeçalho pinta **três stops derivados** + chips translúcidos que
> ninguém gradua — o par que o painel mostra não é o que o cabeçalho renderiza. Este mapa só registra
> que o painel **aconselha**; a superfície bloqueante é a `validation()` (idnumber + SCSS), acima.

---

## `FORM-TPL` — corpo do form de template (`template_dynamic_form.php`)

Abre por `plans.js`: **novo** (`new-template`, `:714-720`, args `{id:0, contextid}`) e **editar**
(`edit-template`, `:721-727`, args `{id}`, título nativo `edittemplate`). **Salvar** →
`reloadKeepingScroll` (`plans.js:206` → `:93-102`: snapshota o scroll das duas regiões, `reloadPane`,
restaura). **Sem toast.** Save server (`form.php:280-310`): `create/update_template` + o
`lp_handler::instance_form_save_with_image` (2-arg) que dispara o evento `template_customfields_updated`,
tudo num retry de `dml_write_exception` (corrida do INSERT id-0 no `customfield_data`). Gate:
`moodle/competency:templatemanage`.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `FORM-TPL-ID` | `[hidden]` | hidden | `form.php:112-113` | `PARAM_INT` | 0 = criar |
| `FORM-TPL-CONTEXTID` | `[hidden]` | hidden | `form.php:114-116` | `PARAM_INT` | escopo do shortname único; 0 → contexto system (gotcha dataset-as-truth) |
| `FORM-TPL-SHORTNAME` | Short name | text | `form.php:118-121` | `PARAM_TEXT` · maxlength 100 | único visível sempre-obrigatório; unicidade server (`shortnametaken`) |
| `FORM-TPL-DESC` | Description | editor | `form.php:127-134` | `PARAM_CLEANHTML` | mídia-por-URL-só (fundação) |
| `FORM-TPL-VISIBLE` | Visible | selectyesno | `form.php:136-138` | default 1 | `addHelpButton` |
| `FORM-TPL-DUEDATE` | Due date | date_time_selector | `form.php:140-141` | `['optional'=>true]` | checkbox de habilitar; 0 = sem prazo. O hero de prazo só aparece na view de **plano**, não no tracker |
| `FORM-TPL-CFIELD` | {headers do core} | customfield (bloco) | `form.php:145` → `lp_handler:169-172` | `customfield_<shortname>` | o **bloco da área lp** (header suprimido). Membros itemizados abaixo |
| `FORM-TPL-DISPLAYMODE` | Modo de exibição | select (customfield) | `form.php:168` | 1=Trilha, 2=Panorama | **o motor da cascata** — 3 `hideIf` dependem dele. O índice 1-based da opção **é** a constante `DISPLAYMODE_*` por construção |
| `FORM-TPL-REDIRECT` | Redirect single course | select (customfield) | `form.php:170-175` | `hideIf displaymode eq 2` | só no modo **Trilha** |
| `FORM-TPL-SHOWRELATED` | Show related | select (customfield) | `form.php:177-182` | `hideIf displaymode eq 1` | só no modo **Panorama**; gate do link abaixo |
| `FORM-TPL-SHOWRELATEDLINK` | Link related | select (customfield) | `form.php:183-195` | **dois** `hideIf`: displaymode eq 1 **e** showrelated eq índice-de-No | só Panorama **e** com Show-related=Sim. O 2º valor é o índice congelado no define — reordenar `showrelated_options()` o segue silenciosamente |
| `FORM-TPL-ENROLFILTER` | Enrollment filter | select (customfield) | `form.php:159-161` | — | âncora do explicador de cascata (inserido acima dele) |
| `FORM-TPL-BGCOLOR` / `-TEXTCOLOR` | custombgcolor / customtextcolor | text (customfield) | `form.php:215-216/221-222` | hex | **o par graduado** — text puro (não colorpicker), decorado por swatch + painel de contraste (defaults `#0f6cbf`/`#ffffff`, `plans.php:271`) |
| `FORM-TPL-SCSS` | Custom SCSS | textarea (customfield) | `form.php:210-211,257-273` | — | só com `enablecustomscss`. Pinado em `FORMAT_PLAIN` no render **e** no `get_data` (4 formas possíveis); **bloqueia** o salvar em erro de compilação |
| `FORM-TPL-CASCADE` | `[sem rótulo]` | static | `form.php:148-164` | — | explicador, `insertElementBefore` acima do `enrollmentfilter` |

**Validação (`form.php:319-337`) — bloqueia:** shortname único no `contextid` → `shortnametaken`;
+ `validate_customscss` (SCSS inválido). **Não** valida o par de cor (aconselha), nem duedate/visible.

**Controles de design:** (1) **Painel de contraste WCAG** + (2) **swatch** — idênticos ao competency,
mesmo `contrast.js`/`colour_swatch`, mesmo relayout, mesmo "aconselha não bloqueia" (`:221-224`/`:215-218`).
(3) **Cascata `hideIf`** dirigida pelo `displaymode` (progressive-disclosure, 3 regras). (4) **SCSS
`FORMAT_PLAIN`** bloqueante. (5) Descrição URL-só. (6) **Sem toast** no salvar (diverge do padrão da
casa; a confirmação é o reload preservando scroll).

---

## `FORM-IMP` — corpo do form de import (`import_framework_dynamic_form.php`)

Abre pelo botão `FWK-IMPORT` (`data-action="import"`, `frameworks.mustache:85-86`) →
`openImportForm` (`frameworks.js:261-278`), args `{contextid}`, título `central_frameworks_import_title`.
Gate: contexto **SYSTEM ou COURSECAT** (senão `invalidcontext`) + `competency:competencymanage`
(`form.php:65-71`) — superset do `tool/lpimportcsv` do core (só system). **Import roda in-request** no
`process_dynamic_submission` (`:148-158`), **sem WS**: lê o CSV do draft, parseia, importa síncrono.
**Sem customfields** — os customfields do plugin viajam como colunas `cf_*` do CSV, aplicadas pelo importer.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `FORM-IMP-CONTEXTID` | `[hidden]` | hidden | `form.php:93-95` | `PARAM_INT` | alvo do import; do `region.dataset.contextid`; fallback system em id ruim |
| `FORM-IMP-FILE` | CSV file | filepicker | `form.php:97-104` | `accepted_types ['.csv','.txt']` | **o controle central.** `required` só client (`:104`) — a **única** validação client. No save, `$data->importfile` é o **draft id**, lido da área draft (`:190-200`) |
| `FORM-IMP-DELIM` | CSV separator | select | `form.php:106-114` | `PARAM_ALPHA` | options `csv_import_reader::get_delimiter_list()` (core). **Default sensível ao idioma:** `listsep==';' ? 'semicolon' : 'comma'` (`:113-114`) — ';' para locais como pt_br |
| `FORM-IMP-ENCODING` | Encoding | select | `form.php:116-123` | `PARAM_RAW` | options `core_text::get_encodings()`; default UTF-8. `RAW` porque nomes de charset têm chars que `ALPHA` cortaria |
| `FORM-IMP-UPDATE` | Update existing by ID number | advcheckbox | `form.php:125-131` | `PARAM_BOOL` | default off. `addHelpButton` explica merge-por-idnumber (existentes atualizadas, novas adicionadas, **nenhuma removida**; off = sempre cria nova). 3º arg do importer (`:155`) |

**Validação (`form.php:167-182`) — server-only, tudo bloqueia:** re-lê o draft e rejeita **vazio** /
**não-parseável** (`central_frameworks_import_invalidfile`) e **sem linha de framework**
(`central_frameworks_import_noframeworkrow`), todos ancorados no `importfile`. Não há validação
"só-aviso" aqui.

**Controles de design:** o upload (`FORM-IMP-FILE`), o **default de delimitador sensível ao idioma**,
o encoding, e o **toggle de merge** (`FORM-IMP-UPDATE`). Sem escala, sem contraste, sem select congelado.
**A UX de loading/feedback** (banner `data-region="import-loading"`, toast `central_frameworks_import_done`,
e o defeito de ARIA do `makeSpinner`) **já está mapeada** em `fwk-frameworks.md:87-102` — cross-ref, não
re-derivada aqui.

---

## Cruzamentos (não contradizer)

- `fwk-frameworks.md` cobre a **casca** da aba (`FWK-ROW-EDIT`, `FWK-IMPORT`, o banner/toast do import,
  o link de escalas). Este mapa cobre os **corpos**; o `FORM-FWK-SCALE-ACTION` aqui é o que o
  `fwk-frameworks.md:82` chama de "form com MOD.SCALE embutido".
- `mod-scale.md` cobre o **modal filho** de escala (o que o `FORM-FWK-SCALE-ACTION` abre). As IDs
  `MOD.SCALE-ACTION/-SUMMARY/-HIDDEN` de lá eram provisórias e **migram** para `FORM-FWK-SCALE-*` aqui.
- `pln-plans.md:293-298` e `est-structure.md:135` citam o painel de contraste e o opener; este mapa dá
  o inventário de campos que faltava, sem re-litigar o achado do contraste.
