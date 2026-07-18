# Mapa de Campos — `BAR` · Contextbar (as-is)

Seletor de contexto renderizado uma vez acima do `dynamic_tabs` (`central.php:134`), **fora**
dos panes das abas. A troca de contexto é 100% client-side: `applyContextToPanes`
(`context.js:139-151`) escreve em **todos** os panes (`.dynamictabs [data-tab-content]`) e só
recarrega o ativo. O select de categoria é sempre renderizado (oculto em modo sistema).

- **Mustache:** [`templates/central/contextbar.mustache`](../../../templates/central/contextbar.mustache)
- **AMD:** [`amd/src/central/context.js`](../../../amd/src/central/context.js)
- **Renderable:** [`classes/output/central/contextbar.php`](../../../classes/output/central/contextbar.php)
- **To-be no DS:** `hierarchy-nav.html` (propõe trilha adaptativa + contexto em card — **diverge** do as-is); `bar-contextbar.html` (as-is ↔ to-be do toggle `BAR-CATHIDDEN`, abaixo).

## Barra

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `BAR-ROOT` | `[sem rótulo]` | região/raiz | `contextbar.mustache:50-56` | `data-region="contextbar"` | carrega `contexttype`, `categoryid`, `activemode` e as duas contagens do sistema; `init` marca `data-initialised="1"` e sai se já marcado (`context.js:289-294`) |
| `BAR-REFRESH` | Atualizar | botão | `contextbar.mustache:88-92` | `data-action="refresh"` · `fa fa-rotate` | str `refresh` (core). Recarrega o **pane ativo** pelo `reloadPane` via `refresh` (`context.js:172-196`), delegado no clique da barra (`:296-306`), com a disciplina de busy do pane de inscrição: desabilita + `fa-spin` num `finally` e devolve o foco a si (o `disabled` o larga no `<body>`, e o `reloadPane` só re-hospeda foco **dentro** do pane). **Não** re-sincroniza o contador da barra — ver a ressalva ao fim |

## Contexto (Sistema / Categoria)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `BAR-CTX-LABEL` | Contexto | label/heading | `contextbar.mustache:58` | str `managecompetencies_context` | rótulo do grupo; a mesma string repete no `aria-label` do `btn-group` (`:59`) |
| `BAR-CTX-01` | Sistema | botão toggle | `contextbar.mustache:60` | `data-context="system"` | `btn-primary` quando `issystem`, senão `btn-outline-secondary`; ícone `fa-globe`; clique → `setContext` (`context.js:204-234`) |
| `BAR-CTX-02` | Categoria de curso | botão toggle | `contextbar.mustache:63` | `data-context="coursecat"` | idem com `iscoursecat`; ícone `fa-folder-open-o`; delegado no clique da barra (`context.js:296-306`) |

## Categoria

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `BAR-CAT-WRAPPER` | `[sem rótulo]` | região | `contextbar.mustache:69` | `data-region="category-wrapper"` | `hidden` se `^iscoursecat`; clonado em `init` como `pristineCategoryNode` (`context.js:312`) e restaurado ao voltar para o modo categoria (`context.js:266-269`) — `core/form-autocomplete` não tem API de reset |
| `BAR-CAT-LABEL` | Categoria de curso | label | `contextbar.mustache:70` | str `managecompetencies_category` | `for="local-dimensions-central-category"` |
| `BAR-CAT-01` | Categoria de curso (select) | select → autocomplete | `contextbar.mustache:73` | `data-region="category-select"` | `form-select`; vira autocomplete via `enhance` (`context.js:280`); `change` → `setCategory` (`context.js:242-249`) |
| `BAR-CAT-PLACEHOLDER` | "Selecione uma categoria de curso" | option | `contextbar.mustache:74` | `value="0"` | placeholder; 0 = sem categoria → `selectedCounts` devolve `null` e o contador some |
| `BAR-CAT-OPTION` | `nome (contagem)` | option (loop) | `contextbar.mustache:76` | `categoryoptions` | `data-name`/`data-frameworkcount`/`data-templatecount`; renderizado com `frameworkcount`, **reescrito** por `renderOptionLabels` conforme a aba ativa (`context.js:124-130`) |

## Categorias ocultas — `BAR-CATHIDDEN` (to-be · proposto 2026-07-18)

> **Implementado (local; pendente push + validação runtime — resync deste mapa para as-is com
> refs após CI verde).** Fecha o item 3 do backlog da Central: o irmão do "Mostrar estruturas
> ocultas" (FWK/EST), agora para **categorias de curso** no picker da barra. Design em
> `bar-contextbar.html`; spec em `docs/superpowers/specs/2026-07-18-central-bar-hidden-categories-design.md` (local).

| ID | Rótulo | Tipo | Origem (to-be) | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `BAR-CATHIDDEN` | Mostrar categorias ocultas | toggle (partial `showhidden_toggle`) | `contextbar.mustache` (bloco dentro da coluna de Contexto, abaixo dos botões) | `data-action="toggle-hidden-cats"` | **reusa o partial compartilhado** `local_dimensions/central/showhidden_toggle` (`{id,label,action,checked}`, mesmo de EST/FWK) via seção `{{#hiddencatstoggle}}` (null → não renderiza = gate de `hashiddencategories`); fica **abaixo do grupo de botões Sistema/Categoria** (`.mt-2`), oculto no modo Sistema (`^iscoursecat`); `<label>` envolvente **real** (o named selector "checkbox" do Behat exige for/envolvente, não `aria-label`); str nova `central_bar_showhiddencategories` |

**Alinhamento (correção pós-runtime, 2026-07-18).** A barra passou de `align-items-end` para
`align-items-start` (rótulos "Contexto"/"Categoria de curso" alinham pelo topo); o toggle desceu
para **dentro da coluna de Contexto** (antes ficava solto à direita do select); `BAR-COUNT-01` e
`BAR-REFRESH` ganharam `align-self-center` (centrados na barra alta); e o **chip da categoria
selecionada** do autocomplete foi reposicionado **abaixo** do input via CSS escopado (styles.css:
`.local-dimensions-central-contextbar [data-region='category-wrapper']` vira coluna +
`.form-autocomplete-selection { order: 1 }`) — depende do DOM do core, revalidar no upgrade.

**Semântica.** Por padrão o picker mostra só categorias visíveis; o toggle revela as `visible=0`
**que o usuário já pode ver** (`make_categories_list()` só as traz para quem tem
`moodle/category:viewhiddencategories`). Sem categoria oculta visível → sem toggle.

**Comportamento (client-side, sem `reloadPane`).** Espelha `applyShowHidden`: o servidor renderiza
todas as options marcando as ocultas com `data-hidden="1"`; o `<select>` mostra só visíveis por
padrão; ligar reconstrói as `<option>` de um snapshot, preservando a seleção. Só a **lista** muda —
`BAR-COUNT-01` é independente (conta o contexto, não categorias).

**Edge.** Categoria selecionada persistida oculta → toggle **inicia ligado** (senão o contexto atual
sumiria da lista).

**Persistência.** Pref `central_nav` (já guarda contexto+categoria), chave `showhiddencats`;
sobrevive sessões/dispositivos. Sanitizar no `helper::get_central_prefs` (toda chave nova entra no
sanitizador). Privacidade já cobre `central_nav`.

**Backend.** `central_category_options()` marca `hidden` por opção; `contextbar.php` expõe
`hashiddencategories` + semeia o estado inicial (pref + edge). Sem WS nova.

## Contador

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `BAR-COUNT-01` | `[sem rótulo]` | região contador | `contextbar.mustache:81-82` | `data-region="context-count"` | `hidden` se `needscategory`; `renderCounter` (`context.js:101-117`) oculta/reexibe conforme `selectedCounts` (`context.js:78-94`) |
| `BAR-COUNT-VALUE` | `[sem rótulo]` | número | `contextbar.mustache:83` | `selectedframeworkcount` | valor inicial vem do servidor; depois `renderCounter` escreve `plans`→templates, senão frameworks (`context.js:116`) |
| `BAR-COUNT-NOUN` | estruturas neste contexto / planos neste contexto | substantivo | `contextbar.mustache:84-85` | str `central_frameworks` / `central_plans` | dois `span[data-mode]`; `renderCounter` mostra só o do modo ativo (`context.js:107-109`). Substantivo **explícito do contexto** (D5 resolvido) — declara que conta o contexto Sistema/Categoria, não a aba |

**Regras de negócio**

- A barra carrega ambas as contagens do sistema (`data-systemframeworkcount`, `data-systemtemplatecount`, `:55-56`) e cada opção carrega as duas suas (`:76`), para alternar sem round-trip.
- **Só conta o visível:** `contextbar.php:78-79` filtra `visible => 1`, e as contagens por categoria idem (`helper.php:1555,1560`). Frameworks/templates ocultos não entram — por isso a aba Estruturas mostra o sufixo "· N ocultas" e a barra não.
- **`data-activemode` é write-only:** o template semeia `structure` (`:54`) e `context.js:324` reescreve a cada troca de aba, mas **nada lê** o atributo (nenhum hit em `amd/src`, `styles.css`, `templates/`, `classes/`). `activeMode()` deriva do pane ativo (`context.js:66-69`) e o valor inicial do contador vem do servidor (`:83`). *(Corrige a regra anterior deste mapa, que dizia que o atributo definia a contagem inicial.)*
- `activeMode()` só distingue `plans`: **as abas Estruturas e Competências caem as duas no ramo padrão `'structure'`** (`context.js:66-69`).
- Troca de aba é ouvida por **jQuery** `shown.bs.tab` sobre `.dynamictabs a[data-toggle="tab"], .dynamictabs a[data-bs-toggle="tab"]` (`context.js:58,323-331`) — o Bootstrap 4 (Moodle 4.5) só emite o evento via jQuery. A restauração da aba salva filtra o mesmo seletor (`context.js:337-344`).
- A barra vive fora dos panes e **não** é re-renderizada num refresh de aba (`context.js:286-287`), então suas contagens permanecem nos valores do page load.

## Decisão (D5, 2026-07-14) — o contador · resolvido (2026-07-17)

> A contextbar conta o **contexto** (Sistema/Categoria), não a aba — logo o número sempre esteve
> certo; o **substantivo** é que lia como se descrevesse a aba. Na aba Competências, `activeMode()`
> cai para `'structure'` e o contador mostra as **estruturas** do contexto enquanto o subheader da
> aba mostra a contagem de **competências** — dois números de escopos diferentes lado a lado.
> **Fix shipado:** o substantivo passou a ser **explícito do contexto** — `central_frameworks` =
> "estruturas neste contexto", `central_plans` = "planos neste contexto" (ambas usadas **só** aqui) —
> então o contador declara o próprio escopo em vez de parecer descrever a aba. O número **não** mudou:
> D5 preserva contar o contexto, e só o rótulo ficou honesto. Foi lang-only (sem bump).
> **Alternativa registrada e descartada:** fazer o contador seguir a aba ativa. Descartada porque
> contraria o propósito declarado da contextbar. Não re-litigar sem mudar esta nota.
> **Contexto:** o hub tem **três** contadores onde o mtube tem um.

**Mecânica (verificada no código):**

- A aba de `data-tab-content="structure"` é **rotulada "Competências"** (`managecompetencies_structure`, via `dynamictabs/structure.php:48-49`) — o shortname diz `structure`, o rótulo diz Competências.
- `activeMode()` (`context.js:66-69`) devolve `'plans'` só quando `tabContent === 'plans'`; a aba Competências cai no ramo padrão `'structure'`.
- Logo `BAR-COUNT-VALUE` = `selectedframeworkcount` e `BAR-COUNT-NOUN` = `central_frameworks` = "estruturas neste contexto".
- O substantivo **casa com o número** (ambos falam de estruturas do contexto) e agora **declara o escopo**, então não lê mais como um número da aba — é essa a leitura que D5 fixa, e o "neste contexto" a torna literal.

**Os três contadores:**

| # | Onde | Origem | Conta |
| --- | --- | --- | --- |
| 1 | contextbar (`BAR-COUNT-01`) | `contextbar.mustache:81-86` | estruturas/planos **visíveis do contexto** |
| 2 | toolbar da aba Estruturas | `frameworks.mustache:77-78` | `central_frameworks_listed` ("Estruturas listadas"): `frameworkcount` + "· N ocultas" |
| 3 | subheader da aba Competências | `structure.mustache:121-122` | `managecompetencies_items` ("itens"): `competencycount` do framework selecionado |

## IMP-05 — atualizar na contextbar (shipado, `mtube: atualizar`)

> Entregue: a contextbar ganhou o botão `BAR-REFRESH` (acima), que recarrega o **pane ativo** pelo
> `reloadPane` que já existia (`tabs.js:69-108`) e que nenhum controle de UI expunha. Sem string
> nova — reusa `{{#str}}refresh, moodle{{/str}}` + `fa fa-rotate`, como o pane de inscrição.
> Copiada a disciplina de busy do mtube (desabilita + `fa-spin` num `finally`); **não** copiado o
> defeito dele de deixar o subtítulo stale — ver a ressalva do contador abaixo.

**O que shipou, verificado:**

- `reloadPane` (`tabs.js:69-108`) tem agora **um** controle de UI que o dispara: o `refresh`
  (`context.js:172-196`), delegado no clique da barra (`:296-306`). Os demais 23 call-sites em 5
  módulos seguem sendo refresh automático pós-ação — nenhum é afordância de UI.
- Ícone + string reusados dos botões `data-action="enrol-refresh"` do pane de inscrição
  (`enrol_methods.mustache:40,48,56,117`), que têm handler próprio e **não** usam `reloadPane` (pane
  de modal, não pane de aba).
- Disciplina de busy: `button.disabled = true` + `fa-spin`, e o `finally` re-habilita, tira o spin
  **e** devolve o foco ao controle quando o `disabled` o largou no `<body>` — o `reloadPane` só
  re-hospeda foco **dentro** do pane (`tabs.js:93-99`), e o botão vive fora dele. Um reload que
  falha solta o controle em vez de girar pra sempre.

**A ressalva do contador — o que NÃO shipou, e por quê.** O `BAR-COUNT-01` **não** é
re-sincronizado pelo refresh, de propósito: a barra vive fora dos panes e não é re-renderizada por
`reloadPane` (`context.js:286-287`), e suas contagens são atributos de render-time (`:55-56`, `:76`,
`:83` no Mustache). Ressincronizá-las exigiria uma contagem fresca do servidor — um WS novo, barrado
pelo congelamento de versão até a 2.0 — ou um recompute client-side que briga com "menos código". **E
não é regressão:** o contador já fica stale hoje a cada add/remove (o mesmo `context.js:286-287`); o
refresh não piora isso, só não o conserta. O análogo ao defeito de subtítulo do mtube fica
**registrado como dívida**, não fingido resolvido — o `BAR-REFRESH` não afirma que o contador atualiza.

> **Alternativa descartada:** ler a contagem fresca do pane recarregado (o toolbar de Estruturas traz
> `frameworkcount`). Rejeitada: o contador da barra conta o **contexto** (D5), que não é o que o
> toolbar do pane conta (frameworks listados, com sufixo de ocultas), então a leitura seria infiel
> para 2 dos 3 modos e acoplaria a barra ao DOM interno do pane. Fica para quando houver fonte de
> contagem fresca.
