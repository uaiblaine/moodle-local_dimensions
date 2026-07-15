# Mapa de Campos — `BAR` · Contextbar (as-is)

Seletor de contexto renderizado uma vez acima do `dynamic_tabs` (`central.php:134`), **fora**
dos panes das abas. A troca de contexto é 100% client-side: `applyContextToPanes`
(`context.js:138-150`) escreve em **todos** os panes (`.dynamictabs [data-tab-content]`) e só
recarrega o ativo. O select de categoria é sempre renderizado (oculto em modo sistema).

- **Mustache:** [`templates/central/contextbar.mustache`](../../../templates/central/contextbar.mustache)
- **AMD:** [`amd/src/central/context.js`](../../../amd/src/central/context.js)
- **Renderable:** [`classes/output/central/contextbar.php`](../../../classes/output/central/contextbar.php)
- **To-be no DS:** `hierarchy-nav.html` (propõe trilha adaptativa + contexto em card — **diverge** do as-is).

## Barra

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `BAR-ROOT` | `[sem rótulo]` | região/raiz | `contextbar.mustache:50-56` | `data-region="contextbar"` | carrega `contexttype`, `categoryid`, `activemode` e as duas contagens do sistema; `init` marca `data-initialised="1"` e sai se já marcado (`context.js:254-258`) |

## Contexto (Sistema / Categoria)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `BAR-CTX-LABEL` | Contexto | label/heading | `contextbar.mustache:58` | str `managecompetencies_context` | rótulo do grupo; a mesma string repete no `aria-label` do `btn-group` (`:59`) |
| `BAR-CTX-01` | Sistema | botão toggle | `contextbar.mustache:60` | `data-context="system"` | `btn-primary` quando `issystem`, senão `btn-outline-secondary`; ícone `fa-globe`; clique → `setContext` (`context.js:168-198`) |
| `BAR-CTX-02` | Categoria de curso | botão toggle | `contextbar.mustache:63` | `data-context="coursecat"` | idem com `iscoursecat`; ícone `fa-folder-open-o`; delegado no clique da barra (`context.js:260-265`) |

## Categoria

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `BAR-CAT-WRAPPER` | `[sem rótulo]` | região | `contextbar.mustache:69` | `data-region="category-wrapper"` | `hidden` se `^iscoursecat`; clonado em `init` como `pristineCategoryNode` (`context.js:271`) e restaurado ao voltar para o modo categoria (`context.js:230-233`) — `core/form-autocomplete` não tem API de reset |
| `BAR-CAT-LABEL` | Categoria de curso | label | `contextbar.mustache:70` | str `managecompetencies_category` | `for="local-dimensions-central-category"` |
| `BAR-CAT-01` | Categoria de curso (select) | select → autocomplete | `contextbar.mustache:73` | `data-region="category-select"` | `form-select`; vira autocomplete via `enhance` (`context.js:244`); `change` → `setCategory` (`context.js:206-213`) |
| `BAR-CAT-PLACEHOLDER` | "Selecione uma categoria de curso" | option | `contextbar.mustache:74` | `value="0"` | placeholder; 0 = sem categoria → `selectedCounts` devolve `null` e o contador some |
| `BAR-CAT-OPTION` | `nome (contagem)` | option (loop) | `contextbar.mustache:76` | `categoryoptions` | `data-name`/`data-frameworkcount`/`data-templatecount`; renderizado com `frameworkcount`, **reescrito** por `renderOptionLabels` conforme a aba ativa (`context.js:123-129`) |

## Contador

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `BAR-COUNT-01` | `[sem rótulo]` | região contador | `contextbar.mustache:81-82` | `data-region="context-count"` | `hidden` se `needscategory`; `renderCounter` (`context.js:100-116`) oculta/reexibe conforme `selectedCounts` (`context.js:77-93`) |
| `BAR-COUNT-VALUE` | `[sem rótulo]` | número | `contextbar.mustache:83` | `selectedframeworkcount` | valor inicial vem do servidor; depois `renderCounter` escreve `plans`→templates, senão frameworks (`context.js:115`) |
| `BAR-COUNT-NOUN` | estruturas / planos | substantivo | `contextbar.mustache:84-85` | str `central_frameworks` / `central_plans` | dois `span[data-mode]`; `renderCounter` mostra só o do modo ativo (`context.js:106-108`) |

**Regras de negócio**

- A barra carrega ambas as contagens do sistema (`data-systemframeworkcount`, `data-systemtemplatecount`, `:55-56`) e cada opção carrega as duas suas (`:76`), para alternar sem round-trip.
- **Só conta o visível:** `contextbar.php:78-79` filtra `visible => 1`, e as contagens por categoria idem (`helper.php:1555,1560`). Frameworks/templates ocultos não entram — por isso a aba Estruturas mostra o sufixo "· N ocultas" e a barra não.
- **`data-activemode` é write-only:** o template semeia `structure` (`:54`) e `context.js:283` reescreve a cada troca de aba, mas **nada lê** o atributo (nenhum hit em `amd/src`, `styles.css`, `templates/`, `classes/`). `activeMode()` deriva do pane ativo (`context.js:65-68`) e o valor inicial do contador vem do servidor (`:83`). *(Corrige a regra anterior deste mapa, que dizia que o atributo definia a contagem inicial.)*
- `activeMode()` só distingue `plans`: **as abas Estruturas e Competências caem as duas no ramo padrão `'structure'`** (`context.js:65-68`).
- Troca de aba é ouvida por **jQuery** `shown.bs.tab` sobre `.dynamictabs a[data-toggle="tab"], .dynamictabs a[data-bs-toggle="tab"]` (`context.js:57,282-290`) — o Bootstrap 4 (Moodle 4.5) só emite o evento via jQuery. A restauração da aba salva filtra o mesmo seletor (`context.js:296-303`).
- A barra vive fora dos panes e **não** é re-renderizada num refresh de aba (`context.js:250-251`), então suas contagens permanecem nos valores do page load.

## Decisão (D5, 2026-07-14) — o contador

> A contextbar conta o **contexto** (Sistema/Categoria), não a aba — logo o número está certo e o
> **substantivo** é que erra na aba Competências, onde `activeMode()` cai para `'structure'` e o
> rótulo diz "estruturas" enquanto o subheader da aba mostra a contagem de competências.
> **Alternativa registrada e descartada:** fazer o contador seguir a aba ativa. Descartada porque
> contraria o propósito declarado da contextbar. Não re-litigar sem mudar esta nota.
> **Contexto:** o hub tem **três** contadores onde o mtube tem um.

**Mecânica (verificada no código):**

- A aba de `data-tab-content="structure"` é **rotulada "Competências"** (`managecompetencies_structure`, via `dynamictabs/structure.php:48-49`) — o shortname diz `structure`, o rótulo diz Competências.
- `activeMode()` (`context.js:65-68`) devolve `'plans'` só quando `tabContent === 'plans'`; a aba Competências cai no ramo padrão `'structure'`.
- Logo `BAR-COUNT-VALUE` = `selectedframeworkcount` e `BAR-COUNT-NOUN` = `central_frameworks` = "estruturas".
- O substantivo **casa com o número** (ambos falam de estruturas do contexto); o que diverge é o substantivo **em relação ao assunto da aba** — é essa a leitura que D5 fixa.

**Os três contadores:**

| # | Onde | Origem | Conta |
| --- | --- | --- | --- |
| 1 | contextbar (`BAR-COUNT-01`) | `contextbar.mustache:81-86` | estruturas/planos **visíveis do contexto** |
| 2 | toolbar da aba Estruturas | `frameworks.mustache:75-76` | `central_frameworks_listed` ("Estruturas listadas"): `frameworkcount` + "· N ocultas" |
| 3 | subheader da aba Competências | `structure.mustache:121-122` | `managecompetencies_items` ("itens"): `competencycount` do framework selecionado |

## to-be (IMP-05, `mtube: refresh`)

> A contextbar ganha um controle de atualizar, reusando o `reloadPane` que já existe
> (`tabs.js:51-66`) e que hoje **nenhum controle de UI expõe**. **Não** vai no sticky-footer: ele é
> escopado por seleção e é limpo na troca de aba. Sem string nova — o pane de inscrição já shipa
> `{{#str}}refresh, moodle{{/str}}` + `fa fa-rotate`. Copiar a disciplina do mtube
> (disabled + `fa-spin` num `finally`); **não** copiar o defeito dele de deixar o subtítulo stale.

**Verificações:**

- `reloadPane` existe (`tabs.js:51-66`) e tem **23 chamadas em 5 módulos** (`competency_browser` 1, `context` 1, `frameworks` 6, `plans` 6, `structure` 9) — todas refresh automático pós-ação. Nenhum controle de UI o dispara: não há afordância de refresh em `contextbar.mustache`, `structure.mustache`, `frameworks.mustache` nem `plans.mustache`.
- Ícone + string já shipados nos **três** botões `data-action="enrol-refresh"` do pane de inscrição (`enrol_methods.mustache:40,48,109`), que têm handler próprio (`enrol_methods.js:915`) e **não** usam `reloadPane` — são pane de modal, não pane de aba.
- **Rastreabilidade das refs do mtube — leia antes de grepar:** o `format_mtube` **não** tem `amd/src` neste checkout, só `amd/build`. As três refs de `course_report.js` abaixo vêm do `sourcesContent` embutido no sourcemap e resolvem por `course/format/mtube/amd/build/features/course_report.min.js.map` → fonte `../../src/features/course_report.js` (370 linhas). Um `grep` no disco por esse `.js` **não acha nada** — isso é esperado, não é ausência.
- Disciplina do mtube (`course_report.js:286-299`): `button.disabled = true` + `icon?.classList.add('fa-spin')` + `try { await refreshActiveTab(…) } finally { button.disabled = false; icon?.classList.remove('fa-spin'); }` — idêntica em **5** módulos (`course_content`, `fab`, `user_report`, `course_report`, `activity_overview_report`). Atenção: os refresh **antigos** do mtube (`ui.js`, `#refresh-section`/`#refresh-activity`) **não** têm essa disciplina — não é deles que o IMP-05 copia.
- O defeito stale do mtube: o `.modal-header` — subtítulo montado de `config.participantcount`/`config.activitycount`, com o próprio botão de refresh ao lado (`:256`) — é construído **uma vez** (`course_report.js:239-262`), e o refresh só recarrega o pane ativo (`refreshActiveTab`, `course_report.js:166-169`). O header nunca é refeito, então o subtítulo mente após a primeira mudança.
- **O análogo aqui:** a barra não é re-renderizada por `reloadPane` (`context.js:250-251`) e suas contagens são atributos de render-time (`:55-56`, `:76`, `:83`). Um refresh que só chame `reloadPane` deixa `BAR-COUNT-01` stale — o controle precisa reatualizar também as contagens da barra.
