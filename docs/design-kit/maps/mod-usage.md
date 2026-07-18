# Mapa de Campos — `MOD.USAGE` · Onde a competência é usada (as-is)

Modal aberto por **um dos três contadores** do card de detalhe da aba Estrutura — Cursos vinculados,
Atividades vinculadas ou Planos de aprendizagem vinculados. Lista, em texto simples, onde aquela
competência aparece. É o modal mais simples do kit e o mais fácil de descrever errado, porque **duas
das suas regras não são visíveis no Mustache**: o web service devolve **as três** listas e o template
renderiza **só a que o usuário clicou**; e as linhas são **deliberadamente não navegáveis** — não
levam ao curso, à atividade nem ao plano.

- **Mustache:** [`competency_usage_modal.mustache`](../../../templates/central/competency_usage_modal.mustache)
  (100) — só o **corpo**; o `Modal.create` é todo em JS. Gatilhos em
  [`structure_detail_content.mustache`](../../../templates/central/structure_detail_content.mustache)
  (`:79-82`, `:91-94`, `:103-106`)
- **AMD:** [`structure.js`](../../../amd/src/central/structure.js) — mapa `USAGE_SECTIONS` em
  `:1190-1195`, `openUsageModal` em `:1197-1232`, despacho em `:1249-1251`. Usa `core/modal` (import
  `:29`), `core/templates` (`:35`), `getString` (`:39`) e `errors.js` (`notifyError`, `:34`)
- **WS:** `local_dimensions_competency_usage` (`db/services.php:90-91` →
  [`classes/external/competency_usage.php`](../../../classes/external/competency_usage.php)) —
  **um WS do plugin, uma chamada, três listas**. Sem WS do core
- **CSS:** **nenhum.** Um `grep -n 'local-dimensions-central-usage' styles.css` não devolve nada — a
  classe do `:51` existe só como gancho semântico. O corpo é **Bootstrap puro**
  (`list-unstyled`, `py-1 border-bottom`, `text-muted small`, `font-monospace`)
- **Behat:** nenhum. Não há `.feature` tocando os contadores
- **Tela no DS:** **nenhuma, de propósito.** É uma lista `<li>` sem decisão de design; desenhá-la
  acrescentaria superfície ao kit sem nada para revisar. As regras estão todas aqui

**Abreviações usadas nas tabelas:** `mustache:` = `templates/central/competency_usage_modal.mustache`
· `js:` = `amd/src/central/structure.js` · `detail:` =
`templates/central/structure_detail_content.mustache` · `php:` =
`classes/external/competency_usage.php`.

> **Mapa novo (2026-07-15) — a superfície não tinha mapa nenhum.** Um
> `grep -rln 'competency_usage_modal' docs/design-kit/` devolvia **vazio** (controle positivo: o mesmo
> grep com `mod-delplans` devolve 7 arquivos), contradizendo o "Todas as superfícies da Central estão
> cobertas" do README. Corrigido aqui e na tabela do README.
>
> **Procedência, medida — o brief desta task errou o commit.** O brief dizia
> "`competency_usage_modal.mustache` (commit `ec028d5`)". O arquivo **nasceu em `6f9fc47`**
> ("Structure tab parity — tree drag-and-drop, equal panes, usage counters", 2026-07-02);
> `ec028d5` é do **mesmo dia** e o **reformou** (`git show --stat ec028d5 -- <arquivo>`:
> **20 inserções, 13 deleções**) — foi ali que nasceram os flags `show*` e, com eles, a regra
> "só a seção clicada". As duas afirmações são verdadeiras em partes diferentes: o arquivo é de
> `6f9fc47`, o **comportamento por seção** é de `ec028d5`.

## Gatilhos (na aba Estrutura, fora do modal)

As três portas **já têm ID** — são do card de detalhe e pertencem ao
[`est-structure.md`](est-structure.md). Este mapa **as referencia**, não as re-emite.

| ID (dono) | Rótulo | Origem | `data-usage` | Regra |
| --- | --- | --- | --- | --- |
| `EST-DETAIL-COURSES` | Cursos vinculados | `detail:79-82` (botão) · `:85` (texto) | `courses` | str `managecompetencies_linkedcourses` |
| `EST-DETAIL-ACTIVITIES` | Atividades vinculadas | `detail:91-94` (botão) · `:97` (texto) | `activities` | str `managecompetencies_linkedactivities` |
| `EST-DETAIL-PLANS` | Planos de aprendizagem vinculados | `detail:103-106` (botão) · `:109` (texto) | `templates` | str `central_structure_linkedplans`. **O `data-usage` diverge do nome:** a UI diz "planos", o dataset diz `templates` — e o mapa `USAGE_SECTIONS` (`js:1194`) casa `templates` → `central_structure_linkedplans` |

**A regra que governa os três:** o contador só é `<button>` sob `{{#linksclickable}}`; sem o flag
ele é uma `<div>` inerte (`detail:84-86`, `:96-98`, `:108-110`). O `MOD.DETAIL` entra com
`linksclickable: false` (`competency_detail.js:275`), então **este modal nunca empilha sobre
aquele** — é o mecanismo, não uma convenção.

## Casca (montada em JS, sem Mustache)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.USAGE-TITLE` | {seção} — {nome} | título | `js:1225-1226` | str de `USAGE_SECTIONS[labelkey]` + `' — '` + `row.dataset.name` | o `title` recebe a **Promise** do `getString` (`Modal.create` aceita); o travessão é **literal no JS**, não vem de string. É o único lugar que diz **qual** seção está aberta — o corpo não tem cabeçalho |
| `MOD.USAGE-MODAL` | — | `core/modal` | `js:1224-1231` | `large: true`, `show: true`, `removeOnClose: true` | `core/modal` **puro** — sem `footer`, sem save/cancel: é leitura. Fecha só pelo `.btn-close` do cabeçalho do core (que **recebe** o restyle de chip azul do plugin, `styles.css:3740` — este modal **não** está no `:not()` da exclusão; quem está é o `MOD.DETAIL`) |
| `MOD.USAGE-ROOT` | `[sem rótulo]` | região/raiz | `mustache:51` | `.local-dimensions-central-usage` | **sem CSS**. Único filho do corpo; as três seções são irmãs dentro dele |

## Seção "Cursos" (`showcourses`)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.USAGE-COURSES` | `[sem rótulo]` | lista | `mustache:54-61` | `ul.list-unstyled.mb-0` | sai sob `{{#showcourses}}{{#hascourses}}` |
| `MOD.USAGE-COURSE-ROW` | {fullname} | linha | `mustache:56-59` | `li.py-1.border-bottom` | **não é link nem botão** — ver a regra 2 abaixo. `name` = `format_string($course->fullname)` no contexto do curso (`php:93`) |
| `MOD.USAGE-COURSE-SHORT` | `[sem rótulo]` | shortname | `mustache:58` | `.font-monospace.small.text-muted.ms-1` | `format_string($course->shortname)` (`php:94`) |
| `MOD.USAGE-EMPTY-COURSES` | Nenhum curso vinculado. | estado vazio | `mustache:64` | str **`central_links_nocourses`** | **assimetria de string:** este vazio **reusa a string do `MOD.LINKS`**; os outros dois têm string própria (`central_usage_*`). Não é bug — é reuso — mas quem editar a string do `MOD.LINKS` muda **este** texto também |

## Seção "Atividades" (`showactivities`)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.USAGE-ACTIVITIES` | `[sem rótulo]` | lista | `mustache:70-78` | `ul.list-unstyled.mb-0` | sai sob `{{#showactivities}}{{#hasactivities}}` |
| `MOD.USAGE-ACT-ROW` | {nome do módulo} | linha | `mustache:72-76` | `li.py-1.border-bottom` · `cmid` | o `cmid` **vai no contexto mas não é usado** pelo template — nenhum atributo o carrega. É a prova mais direta da regra 2: o dado para navegar **está lá** e o template escolhe não usá-lo |
| `MOD.USAGE-ACT-COURSE` | — {fullname} | curso da atividade | `mustache:74` | `.small.text-muted.ms-1` | o travessão é **literal no Mustache** (`:74`), não vem de string — não localiza |
| `MOD.USAGE-ACT-SHORT` | `[sem rótulo]` | shortname do curso | `mustache:75` | `.font-monospace.small.text-muted.ms-1` | |
| `MOD.USAGE-EMPTY-ACTIVITIES` | Nenhuma atividade vinculada. | estado vazio | `mustache:81` | str `central_usage_noactivities` | |

## Seção "Planos" (`showtemplates`)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.USAGE-PLANS` | `[sem rótulo]` | lista | `mustache:87-94` | `ul.list-unstyled.mb-0` | sai sob `{{#showtemplates}}{{#hastemplates}}` |
| `MOD.USAGE-PLAN-ROW` | {shortname do template} | linha | `mustache:89-92` | `li.py-1.border-bottom` | `format_string($template->get('shortname'))` (`php:122`) — **sem `['context' => …]`**, ao contrário dos cursos (`php:93-94`) e das atividades (`php:110`), que passam o contexto do curso. Aqui cai no contexto default do `$PAGE` |
| `MOD.USAGE-PLAN-HIDDEN` | Oculto | badge | `mustache:91` | `.badge.bg-secondary.ms-1` · str `hidden, tool_lp` | sai sob `{{^visible}}` — **só** o template oculto ganha badge; o visível não ganha nada. String do **`tool_lp`**, não do plugin |
| `MOD.USAGE-EMPTY-PLANS` | Não está em nenhum plano de aprendizagem. | estado vazio | `mustache:97` | str `central_usage_noplans` | |

## Regras de negócio (verificadas no código)

### 1. O WS devolve as três listas; o modal mostra uma

`openUsageModal` (`js:1207-1232`) faz **uma** chamada a `local_dimensions_competency_usage`
(`js:1209-1212`) e passa ao template **os três arrays inteiros** — `courses`, `activities` e
`templates` (`js:1216`, `:1219`, `:1222`) — junto com três flags `show*` dos quais **exatamente um é
`true`** (`js:1214`, `:1217`, `:1220`, cada um um `labelkey === '…'`). O `php:127` confirma o outro
lado: `return ['courses' => …, 'activities' => …, 'templates' => …]`, sempre os três.

Ou seja: **o custo é sempre o de três listas; o proveito é o de uma.** Trocar de contador refaz a
chamada inteira, porque o modal é `removeOnClose` e não há cache. Não é acidente — o
`ec028d5` foi exatamente a mudança que introduziu os `show*` num template que antes renderizava
tudo. O que ficou por fazer é o WS aceitar a seção como argumento, não o template descartá-la.

### 2. As linhas não navegam — e não é esquecimento

Cada linha é `<li class="py-1 border-bottom">` dentro de um `<ul class="list-unstyled mb-0">`
(`mustache:54-61`, `:70-78`, `:87-94`). **Nenhuma** carrega `<a href>`, `<button>` ou `data-action`.
O modal informa *onde* a competência é usada e **não leva até lá**.

A prova de que é decisão, não descuido: o WS **exporta os ids** — `id` do curso (`php:96`), `cmid`
da atividade (`php:109`), `id` do template (`php:121`) — e os declara no `execute_returns`
(`php:137-149`). O template **recebe** os três e **não usa nenhum**. Construir o link seria
`/course/view.php?id={{id}}` e `/mod/…/view.php?id={{cmid}}` — o dado está no contexto.

O motivo plausível é o de sempre num modal: navegar **destrói** a Central (a árvore, a seleção, a
expansão, o scroll). É o mesmo raciocínio do `MOD.LINKS`, que também lista e não navega. Registrado
como **decisão**, não como lacuna — a discussão (abrir em nova aba? `target="_blank"`?) é de to-be.

### 3. Seção desconhecida cai em "cursos", calada

`const labelkey = USAGE_SECTIONS[section] ? section : 'courses';` (`js:1208`). Um `data-usage` com
lixo (ou ausente) **não** dá erro: abre a lista de cursos com o título "Cursos vinculados". Como o
único emissor é o próprio Mustache (`detail:82`, `:94`, `:106`), o galho é defensivo — mas é o que
segura um `data-usage` renomeado num lado só.

### 4. Atividade invisível é consequência de curso invisível — estruturalmente

`api::list_courses_using_competency` (`php:91`) já vem filtrada pelas capabilities por curso do
chamador (comentário `php:88`). As atividades são colhidas **dentro** desse laço (`php:103-114`),
`get_fast_modinfo` por curso (`php:102`). Então **a lista de atividades é função da lista de
cursos**: um curso que o usuário não pode ver não contribui com nenhuma atividade — não por um
filtro de atividade, mas porque o laço nunca chega lá. Um `$cm` ausente do `modinfo` é pulado
calado (`php:104-106`).

**Os planos não seguem essa regra.** `api::list_templates_using_competency` (`php:119`) entra sem
filtro por template — o único portão é o global, lá em cima: `require_capability(
'moodle/competency:competencyview')` no **contexto do sistema** (`php:70-72`), mais um
`competency_framework::can_read_context` no contexto do **framework** (`php:79-86`), que lança
`required_capability_exception` quando falha. Por isso o `visible` é exportado (`php:123`) e vira o
badge "Oculto": quem chega aqui **vê** o template oculto, e o badge é o que conta a verdade.

### 5. O título é a única pista da seção

O corpo não tem cabeçalho de seção: aberto em "Atividades", o `<ul>` é indistinguível do de
"Cursos" (mesmas classes, mesma forma). Quem diz é o `MOD.USAGE-TITLE` (`js:1225-1226`) — e ele
depende do `USAGE_SECTIONS[labelkey]`. O rótulo do contador e o título do modal saem da **mesma
string** (o comentário do `js:1190` registra isso: "lang key of its title (also the counter
label)"), então nunca divergem.
