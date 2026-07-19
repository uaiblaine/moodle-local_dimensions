# Design — Alinhar o admin kit da Central ao padrão de qualidade do mtube

Data: 2026-07-14
Escopo: **somente o design kit** (`docs/design-kit/` + projeto Claude Design
`35784af0-29b9-434f-b3f0-9618fa749829`). Nenhuma mudança de código do plugin nesta rodada.
Baseline do as-is: **`f84d30a`** / `$plugin->version = 2026071306`.

## Problema

O padrão de qualidade de UI/UX da casa passa a se espelhar nos modais do
`course/format/mtube`, cuja versão atual do `modal=course-report` reformulou o cabeçalho
(botões de atualizar conteúdo, expandir e fechar), criou um **rodapé de modal** para os links
administrativos que antes ficavam no cabeçalho, e tratou cores, tamanhos, animações, cantos
arredondados, load na troca de aba e contador no rodapé.

O admin kit da Central precisa (a) refletir o **estado atual** do plugin e (b) **mapear** as
melhorias inspiradas no mtube. Uma auditoria de 8 agentes sobre os dois plugins (15 eixos)
mostrou que o kit está amplamente defasado e que o caminho de implementação do mtube **não**
deve ser copiado.

## O que a comparação estabeleceu

### O shell do mtube não é portável (rejeitado)

O `modal=course-report` não tem renderable PHP nem Mustache. É `ModalFactory.create()` seguido
de cirurgia imperativa em template literals — `root.find('.modal-header').empty()`,
`root.find('.modal-dialog').addClass('modal-xl')`. Grep por
`modal-header|modal-footer|modal-dialog` em todo o `templates/` do mtube retorna **zero**.

Isso escapa do step `mustache` do CI, do `{{#str}}` e de qualquer Example context. É também por
que o `min-width-0` vive no cabeçalho dele como classe que não existe em lugar nenhum — um
no-op que ninguém pegou. A Central usa Mustache + `core/modal*`, que é a forma correta de
plugin Moodle e a que menos código exige. **Mantida.**

### Mas o header→rodapé se aplica — e acopla com os botões novos

A Central tem exatamente o padrão de que o mtube fugiu: `participants_manager` injeta até 4
links administrativos capability-gated no cabeçalho (`local-dimensions-headerlink`,
`target="_blank"`), e o ModalForm de framework injeta "Abrir página de escalas".

O mtube moveu os links pro rodapé **porque o cabeçalho encheu** (refresh + expand + close +
trilha de abas). Se a Central ganhar atualizar e expandir no cabeçalho, ele enche pelo mesmo
motivo. **As duas mudanças são uma decisão só**, não duas — e é por isso que foram aprovadas
juntas.

**Precisão (Task 3, arbitrado abrindo o código) — separe o estado final do porquê:**

- **O estado final é FATO, e é provável.** `features/course_report.js:223-229` muta o
  `.modal-footer` em runtime (com `justify-content-between`); `activity_overview_table.js:263-276`
  copia os links de `templates/activity_overview/footer_actions_source.mustache` (cujo docblock diz
  "Hidden action links copied into the activity overview modal footer when this tab is active");
  8 templates de `course_report/*` o preenchem — relatório do avaliador, livro de notas, logs,
  emblemas, certificados, competências, frequência, conclusão. São **13 de 14** âncoras
  `btn btn-link p-0` + `target="_blank" rel="noopener"` + `fa fa-external-link ms-1`. E o
  cabeçalho (`course_report.js:248-270`) é `[ícone] título+subtítulo … [refresh] [expand/narrow]
  [close]` — **sem** links administrativos. Isso **é** o D2.
- **O "porque encheu" é HISTÓRIA, não fato verificável.** O mtube não é versionado dentro deste
  checkout — não há histórico que prove um *movimento*; só o estado final bate. O usuário afirmou
  a causa e conhece o plugin, então é plausível; mas **nenhum card pode afirmá-la**.
- **O D2 não precisa da causa.** Ele se sustenta na aritmética do próprio hub: 4 links
  capability-gated + 3 botões de cabeçalho não cabem. É esse o argumento que o card faz.
- Nota: o recon dizia "12 de 14" — o número real é **13 de 14**. Errado por um.

### A lacuna mais afiada não é estética

> **CORREÇÃO (Task 7, 2026-07-14) — a premissa abaixo está errada e o alvo mudou.**
> **A troca de aba de verdade JÁ tem loading, e vem do core:** `dynamic_tabs.js:92-97` liga
> `shown.bs.tab` → `loadTab` → `addIconToContainer` (`:153`), e `:88` apaga o pane anterior. Ou
> seja, quem clica numa aba **vê** estado de carregamento hoje.
> **A lacuna real é o `reloadPane` do plugin** (`tabs.js:51-66`): ele é *o `loadTab` do core menos
> o ícone de loading*, e é ele que roda nos **23 call sites** (structure 9, frameworks 6, plans 6,
> browser 1, context 1) — todos refreshes automáticos pós-ação. É aí que o conteúdo velho fica
> parado sem sinal nenhum.
> **Consequência prática:** "loading na troca de aba" é a formulação errada em todo lugar onde
> aparece. O alvo certo é **"loading no `reloadPane`"**. Vale também para a Task 10 (mount
> preguiçoso do modal de participantes), que precisa ser reavaliada com esta lente.

A Central **não tem nenhum estado de load na troca de aba**, nas duas camadas de aba:

- `tabs.js:37-66` (`reloadPane`) faz `await getContent` → `Templates.replaceNodeContents`. Sem
  spinner, sem `aria-busy`, sem disabled. O conteúdo velho fica parado.
- `participants_manager.js:171-185` (`ensureMounted`) monta o pane na primeira ativação **sem
  placeholder** — o pane fica vazio até o WS resolver.

O mtube resolve com 2 linhas de CSS (`.tab-pane.mtube-dynamic-tab-loading { min-height: 12rem;
position: relative; }`) + um cover com `spinner-border`. Portar o mecanismo, **não o bug**: ele
remove a classe só no caminho de sucesso, então um load que falha deixa o pane girando pra
sempre, sem retry.

**Correção (Task 2, 2026-07-14) — o veredito sobre o mtube é dividido, não é um lado só.**
Verificado recuperando **8.695 linhas de ES6** dos 17 `.min.js.map` via
`jq -r '.sourcesContent[0]'` (o mtube não tem `amd/src`, mas os maps carregam `sourcesContent` —
o JS **é** auditável; quem disser o contrário não tentou):

- **JS do mtube = contra-exemplo APENAS no core, e o escopo importa.** Reconstruindo os **79**
  arquivos-fonte (não os 17 do primeiro corte): os **17 módulos core** (`main.js`, `ui.js`,
  `actions.js`…) têm 8.695 linhas e **zero** `finally` — o `#background-loading` só é escondido em
  `main.js:946`, dentro do callback de **sucesso** do iframe (iframe falha → spinner eterno). Mas
  `features/*.js` são **62 arquivos, 20.615 linhas, com 60 blocos `finally`**. Ou seja: a
  disciplina existe e é a norma no mtube; ela some exatamente **onde mora o estado de load**.
  Escrito sem escopo ("o JS do mtube não faz isso"), é falso para a maioria do código — e a regra
  fica **mais** forte com a versão honesta, não menos.
- **Templates do mtube = o precedente positivo.** `fakeactivities.mustache` ship
  `aria-busy="true"` + `aria-live="polite"` + skeleton — o que o hub não tem em lugar nenhum.
- **O hub não é um vazio de disciplina.** Já usa `finally` em **5** caminhos busy, e o
  `downloadFramework()` é exemplar. O que falta não é a disciplina: é a **cobertura** (o
  `reloadPane` não tem estado busy) e a **unificação**.
- **Os três tratamentos one-off são dois, não três.** O banner do import e o spinner do export
  chamam um `makeSpinner()` **compartilhado** (`frameworks.js:213`, usado em `:236` e `:316`);
  o pane de inscrição tem **dois** sítios (`enrol_methods.js:342` por linha +
  `enrol_methods.mustache:116` no contador). São **2 vocabulários em 4 sítios**.
- **`aria-busy` aparece em ZERO arquivos do plugin.** Esta é a lacuna mais concreta, e o recon
  não a tinha achado.

### Dois bugs reais — corrigidos em `f84d30a` (fora desta rodada, já resolvidos)

A auditoria achou dois pontos que assumiam Bootstrap 5 e estavam silenciosamente mortos no 4.5.
Foram corrigidos numa sessão paralela **antes** deste kit começar; ficam registrados aqui porque
mudaram o as-is que a varredura precisa espelhar.

1. **`central/context.js`** casava as abas só por `data-bs-toggle` + `addEventListener`. O core
   emite `data-toggle` no 4.5 → seletor casava zero; e o BS4 dispara o evento via jQuery, que
   listener nativo não ouve. Consequência: contador não seguia Estruturas/Planos, `saveNav` não
   rodava, restore da aba salva não disparava. **Corrigido**: seletor casa ambos os atributos
   (`context.js:57`) e o bind é jQuery (`:282`) — o BS5 dispara evento jQuery **e** nativo, então
   um listener jQuery cobre os dois branches.
2. **`--bs-modal-width`** dimensionava o modal de participantes. **Confirmado real** contra o
   v4.5.12: o 4.5 não define nenhuma custom property `--bs-*` de modal, então o modal caía para
   `$modal-md` (500px) com suas quatro abas e grids. **Corrigido melhor do que o proposto**: as
   duas media queries eram clone exato do `modal-xl` do próprio Bootstrap (800px/1140px,
   idêntico no 4 e no 5) — foram removidas e a classe entrou no lugar
   (`participants_manager.js:153`), em vez do `max-width` escopado que este spec sugeria. Menos
   código.

Um terceiro ponto, que a auditoria **não** pegou, saiu junto: a regra vizinha de altura lia
`--bs-modal-margin`, também BS5-only, e degradava para `height:auto` no 4.5. Agora usa
`height: calc(100% - var(--bs-modal-margin, 1.75rem) * 2)` — fallback para o
`$modal-dialog-margin-y-sm-up` do BS4, e CI-clean (`calc()` é aceito).

### O que explicitamente NÃO se traz do mtube

| Padrão | Por que não |
|---|---|
| Animação *jelly* (`jellyIn`/`jellyOut`/`jellyDeny`, spring 450ms com overshoot) | Personalidade de formato de curso. Num hub admin alcançado pela árvore de administração, 17 modais quicando é ruído. |
| Cache por-pane (`pane.dataset.loaded`) | As abas da Central mudam a cada save vindo de 17 modais. Cachear traria dado velho — que é o defeito **vivo** do próprio mtube (`refreshActiveTab` limpa só o pane ativo; irmãos ficam stale e o subtítulo do cabeçalho nunca atualiza). |
| Launcher de deep-link (`?modal=…&tab=…` + `replaceState`) | Existe porque modal não tem URL. A Central **é** uma URL. Brigaria com a preferência de aba salva sobre quem vence no load. |
| Dropdown de overflow por ResizeObserver | ~130 linhas de medição para três rótulos curtos que nunca transbordam. |
| Substituir o `.btn-close` do core (`.modal-header').empty()`) | Joga fora a wiring de dismiss e a a11y. A Central **restiliza** — e ganha nesse eixo. |
| `!important` (todas as regras `.fullscreen` do mtube) e `min()` em `height` | Reprovam no stylelint do CI (`declaration-no-important`, `csstree/validator`). |
| `localStorage` para o estado de expandir | Não segue o usuário entre navegadores — estado de usuário vai para preferência de usuário (`setUserPreference`). **Correção (Task 3, verificado):** o `40fb4ad` removeu **`sessionStorage`**, não `localStorage`, e `localStorage` segue vivo no `pane_resizer.js`. O argumento vale; o fato que eu citava estava errado. |

## Invariante — o sticky-footer da página

**Os botões do sticky-footer não são layout: são o lançador de ~10 dos 17 modais do hub.**
Verificado em 2026-07-14:

- `structure_footer_actions.mustache`: `links` → MOD.LINKS, `related` → MOD.RELATED, `rules` →
  MOD.RULE, `moveto` → MOD.MOVETO, mais `edit`/`addchild` (ModalForm) e `delete`.
- `plans.mustache`: `browse-frameworks` → MOD.BROWSER, `manage-participants` → MOD.PART,
  `duplicate-template`, `delete-template` → MOD.DELPLANS, `edit-template` (ModalForm).
- `frameworks_footer_actions.mustache`: `edit` (ModalForm), `duplicate`, `visibility`, `delete`.

Remover um botão dali deixa um modal **inalcançável**. Nenhum painel to-be pode propor isso, e
nenhum `.dc.html` do Claude Design que mostre kebab por linha deve ser seguido nesse ponto
(ver [[dimensions-sticky-footer-preference]] e o incidente de 2026-07-09).

**Nota de desambiguação — há dois "rodapés" neste design e eles não se tocam:**

| | Sticky-footer da **página** | Rodapé do **modal** |
|---|---|---|
| Origem | `\core\output\sticky_footer`, um por página, `central.php` + `action_footer.js` | `.modal-footer` do core, dentro do diálogo |
| Conteúdo | Ações do item selecionado — **lança os modais** | Links administrativos + ação primária |
| Nesta rodada | **Intocado.** Ganha um card que documenta e protege o padrão | Recebe os links que hoje estão no cabeçalho |

## Decisões tomadas

| # | Decisão | Alternativas descartadas |
|---|---|---|
| D1 | Escopo: **só o kit**. Melhorias ficam mapeadas como to-be; código vira fatias depois | Kit + bugs; kit + implementação |
| D2 | Shell to-be: cabeçalho `atualizar + expandir + fechar` **e** links descendo pro rodapé, acoplados | Só expandir; só mapear e decidir depois |
| D3 | Abordagem **A** — fundações primeiro, depois varredura por superfície | B (camadas: as-is todos, depois to-be); C (superfície a superfície sem fundações) |
| D4 | To-be **aplicado + badge de origem** (`mtube: refresh`) em cada elemento importado | Só aplicado, sem marcação; terceiro painel separado |
| D5 | Contador da contextbar: **o número está certo, o substantivo é que erra** | "Seguir a aba ativa" (anotada como alternativa no mapa) |

**Sobre D5 (a única decisão em que o recon não conseguiu inferir a intenção):** a Central tem
três contadores onde o mtube tem um, e o da contextbar mente na aba Competências —
`activeMode()` cai para `'structure'` e mostra a contagem de *frameworks* enquanto o subheader
da aba mostra a de *competências*. Duas leituras são defensáveis e dão desenhos diferentes. A
escolhida preserva o propósito declarado da contextbar (contar o **contexto** Sistema/Categoria,
não a aba). A alternativa fica registrada em `maps/bar-contextbar.md` para não ser
re-litigada.

## Seção 1 — Fundações

### `tokens.html` — seção "Movimento" (nova)

Hoje: **12** durações distintas na Central sem vocabulário — verificado em `f84d30a`, não estimado
(0.12s, 0.15s, 0.18s, 0.2s, 0.25s, 0.3s, 0.45s, 0.5s, 0.6s, 100ms, 120ms, 200ms) —, com fundos de
hover de mesmo propósito usando 0.12s, **0.15s e 0.2s** em componentes diferentes. Easings quase
todos `ease` cru (~30 sítios).

| Token | Valor | Ancoragem |
|---|---|---|
| `--mds-motion-fast` | `150ms` | hover, foco, cor — bate com o `.15s` do Bootstrap |
| `--mds-motion-base` | `250ms` | layout, indicador de aba |
| `--mds-motion-flash` | `1500ms` | confirmação in-place — valor **já shipado** nas 10 cópias (6 módulos) |
| `--mds-motion-ease` | `cubic-bezier(0.4, 0, 0.2, 1)` | Material standard, já usado no FAB |

**Sem token de spinner:** o loading usa o `spinner-border` do Bootstrap, que traz o próprio
`.75s linear infinite` — nenhuma keyframe para escrever e nenhuma dívida de reduced-motion.
Entra só `--mds-loading-min-height: 12rem` (valor do mtube).

Duas honestidades que o card precisa declarar, sob pena de o kit virar descrição do código em
vez de alvo:

1. `--mds-motion-flash` é consumido por **JS (WAAPI)**, não por CSS. Ele é a referência única
   para deduplicar as seis cópias do `flashRow` num helper só.
2. Esses tokens são consumidos por **zero** regras shipadas hoje. Escopo de adoção: superfícies
   novas ou reformuladas apenas — retro-tokenizar todo o CSS shipado briga com a regra de menos
   código. (Não citar contagem de blocos: nenhum método de contagem reproduz um número estável —
   787/776/762 conforme o critério. Um número que ninguém reproduz é pior que nenhum.)

Junto entra a regra `prefers-reduced-motion` que hoje existe em dois blocos (2601, 3028) e não
cobre nenhuma das quatro keyframes.

### `states.html` — estado carregando/busy (novo)

Cobre default/hover/active/disabled + foco e para aí. Sem estado busy — que é exatamente a
lacuna, e a razão de o plugin shipar três tratamentos one-off não relacionados (banner do
import, spinner inline do export, `fa fa-spinner fa-spin` do pane de inscrição).

Duas formas, que é o que o hub precisa:
- **pane-level**: placeholder `spinner-border` + `aria-busy`
- **button-level**: disabled + spinner (a disciplina do mtube — `button.disabled = true;
  icon.classList.add('fa-spin')` num `finally`, não no caminho de sucesso)

Dobrar isso aqui em vez de criar `loading-state.html` custa zero card novo — o kit fecha esta
rodada em **dez** componentes (os nove atuais + `sticky-footer.html`), e o estado busy não
merece um décimo-primeiro quando o card de estados existe justamente para isto.

### `modal-shell.html` — o to-be acoplado (D2)

Hoje o card modela um shell genérico (ícone, título, quadrado `.m-x` de fechar, slot, rodapé
Cancelar/Salvar) que não bate com nenhum dos 17 modais shipados e esconde as três coisas que
de fato variam.

**Cabeçalho:** `[ícone] Título … [atualizar] [expandir] [fechar]` — 36×36 (30×30 abaixo de
768px), escopados em `.local-dimensions-*` para vencer o `.btn` por especificidade, **sem
`!important`**. Expandir/estreitar como **dois botões sempre presentes** com o CSS escolhendo
qual mostrar (truque do mtube: zero JS de troca de ícone). Persistência via preferência de
usuário (`preferences.js` → `setUserPreference`), **não** localStorage.

O card passa a desenhar o **fechar real que já é shipado**, hoje ausente: `.btn-close` →
1.75rem, radius 8px, `background-color:#e7f0f9`, pseudo-elemento FA `content:'\f00d'` em
#0f4d85 — escolhido porque o stylelint do Moodle proíbe SVG inline em data URI.

**Slot de ação no cabeçalho** documentado (é onde hoje entram os 4 headerlinks
capability-gated).

**O que D2 aposenta, e tem dois consumidores — não um.** Os headerlinks são governados pela
classe **compartilhada** `local-dimensions-headerlink-modal`, adicionada em
`participants_manager.js:153` e também usada pelo ModalForm de framework; o CSS dela
("Shared header-link modals (participants tabs, framework form)") encolhe o título para o link
"abrir a página admin do core" encostar no fechar. Mover os links pro rodapé **retira a razão de
existir dessa classe e da regra de título**. O card to-be precisa dizer isso, senão a
implementação move o link e deixa o CSS órfão empurrando o título à toa.

**Rodapé** com a bifurcação real, hoje mascarada: `ModalSaveCancel`/`ModalDeleteCancel`/
`ModalForm` têm rodapé; **sete** superfícies `core/modal` puras shipam rodapé **vazio** com a
ação primária no corpo. No to-be: links administrativos à esquerda (`btn btn-link p-0` +
`fa fa-external-link`, `target="_blank" rel="noopener"`), ação primária à direita,
`justify-content-between`.

### `sticky-footer.html` — card novo

O padrão mais distintivo do hub não tem card enquanto nove componentes menos importantes têm.
Desenha as **três variantes reais** (frameworks / estrutura / planos) no padrão cru do core
(`btn py-0 d-flex flex-column align-items-center`, ícone acima do rótulo centrado, sem variante
de cor), com `#sticky-footer .local-dimensions-central-footer-actions { justify-content: safe
center; overflow-x: auto; }` (o seletor de id vence deliberadamente o `overflow:hidden` do
Boost) e o colapso sr-only para ícone-só abaixo de 767.98px.

**O card carrega o invariante escrito**: estes botões lançam os modais; nunca kebab por linha;
nunca remover.

### `moodle-ds-alignment.md` — seção "restrições de plataforma"

O documento mais forte do kit e o lugar certo para registrar o que decide o que pode ser
construído. Hoje não diz nada sobre:

- **Boundary do stylelint do CI**: nunca `!important`; nunca `clamp()`/`min()`/`max()` em
  propriedades de altura (`csstree/validator`); `calc()` aceito.
- **BS4 vs BS5** (acabou de produzir dois defeitos vivos, ver `f84d30a`): componentes wired por
  markup precisam de **ambos** `data-toggle` e `data-bs-toggle` e de ambas
  `dropdown-menu-right`/`-end`; seletores JS precisam casar os dois.

Três fatos de plataforma **verificados** contra o v4.5.12 e o checkout 5.1 em `f84d30a` — é o
tipo de coisa que só se descobre uma vez, e hoje não está escrita em lugar nenhum:

| Fato | Consequência |
|---|---|
| O 4.5 **não define nenhuma custom property `--bs-*` de modal** (`--bs-modal-width`, `--bs-modal-margin`…) | Nunca dimensionar modal por var BS5. Use as classes do próprio Bootstrap (`modal-xl` é idêntico no 4 e no 5) ou dê fallback: `var(--bs-modal-margin, 1.75rem)` |
| O BS5 (`EventHandler.trigger`) dispara **evento jQuery E nativo**; o BS4 dispara **só jQuery** | Um listener **jQuery** cobre os dois branches. `addEventListener` cobre só o 5.x — é a assimetria que matou o `context.js` |
| `lib/amd/src/first.js` seta `window.jQuery`, então o BS5 **sempre** toma o caminho jQuery | O bind jQuery não é gambiarra de compatibilidade: é o caminho que o core garante nos dois |

Registrar uma vez, em vez de re-litigar por superfície.

## Seção 2 — Varredura por superfície

Doze superfícies, **mapa e tela juntos**. As-is conferido contra o código shipado (é o que dá o
valor de "base limpa de revisão"); to-be consumindo o vocabulário da Seção 1, com badge de
origem (D4) em cada elemento vindo do mtube.

Distribuição das sete melhorias com superfície no kit:

**Caveat do IMP-06 (Task 12, verificado — a chamada se reusa, a fiação não).** O
`ModalSaveCancel` registra `registerCloseOnSave`: **salvar fecha o modal** a menos que se chame
`preventDefault()`. O `MOD.RELATED` precisa **ficar aberto** (toast, flash da linha e empty-state
acontecem nele), então exige um `preventDefault` **incondicional** — diferente do
`competency_browser.js:122`, que fecha de propósito. Não é copiar o call site: é copiar a chamada
e refazer a fiação. E o mecanismo que barateia tudo: o `modal.mustache:58-62` **sempre** renderiza
o `.modal-footer`; o `show()` só o esconde porque `hasFooterContent()` (`modal.js:686` =
`getFooter().children().length`) dá zero (`:875-879`). Dar um filho ao rodapé faz o core revelá-lo
sozinho.

| Melhoria | Telas/mapas afetados |
|---|---|
| Loading na troca de aba | `est-structure`, `fwk-frameworks`, `pln-plans`, `mod-participants` |
| Refresh na contextbar | `bar-contextbar` + as 3 abas |
| Ação primária no rodapé do modal | `mod-related`, `modal-shell` |
| Contador verdadeiro (D5) | `bar-contextbar`, `est-structure`, `fwk-frameworks` |
| Expandir/estreitar | `mod-participants`, `mod-links` |
| Ícones + indicador nas abas | `hierarchy-nav` + as 3 abas |
| Tokens de movimento | `tokens` |

Nota sobre o refresh: a colocação **não** é o sticky-footer (que é escopado por seleção e é
limpo na troca de aba); é a contextbar, que já hospeda o contador. Não precisa de string nova —
o pane de inscrição já shipa `{{#str}}refresh, moodle{{/str}}` + `fa fa-rotate`.

Nota sobre o loading: escopar com cuidado. `reloadKeepingScroll` (planos) e `refreshNode`
(estrutura) são caminhos in-place deliberados — um spinner de pane inteiro ali seria regressão.

## Seção 3 — Criações

| Arquivo | Por quê |
|---|---|
| `sticky-footer.html` | ver Seção 1 |
| `maps/mod-usage.md` | `competency_usage_modal` (ec028d5) não tem mapa — contradiz o README:48. Precisa registrar as duas regras invisíveis no template: só a seção clicada renderiza embora a WS retorne as três, e as linhas são deliberadamente não-navegáveis |
| `maps/mod-moveto.md` | `move_competency_modal` tem **dois** call sites (plans.js:558, structure.js:972) e nenhum mapa. Deve registrar `EST-DETAIL-MOVEUP`/`MOVEDOWN` como **aposentados**, em vez de deixá-los pendurados no `est-structure.md` |
| `maps/mod-structrelated.md` | `structure_related_modal` + chips + `data-action="related"` (47677dd) sem mapa e sem IDs. É o único lugar onde o contrato de CSS seria escrito: cabeçalho `display:none`, diálogo capado em 620px com radius 24px, corpo sem padding, botão de fechar colorido em JS pelo textcolor da competência, e a exclusão explícita do restyle global do `.btn-close` |
| `screens/mod-structrelated.html` | superfície mais incomum do plugin — diálogo sem cabeçalho onde **o card é o diálogo**. Único painel as-is (nada é proposto) |

**Deliberadamente sem `screens/mod-usage.html` e `screens/mod-moveto.html`**: uma lista simples
e um select, inteiramente descritos pelos mapas. Desenhá-los adicionaria superfície de kit sem
decisão de design por trás.

## Seção 4 — Sync e README

`README.md` atualizado, e sync via DesignSync (`finalize_plan` → `write_files` com `localPath`,
que não traz o conteúdo pro contexto do modelo). Os `.html` sobem como cards via marcador
`@dsCard` na primeira linha; os `maps/*.md` ficam só no repo, como já é a convenção.

O README passa a registrar duas lacunas que o recon expôs e que hoje ninguém sabe que existem:

1. **O kit não mapeia nenhum corpo de `dynamic_form`** — por isso o lado *formulário* do
   `MOD.SCALE` (select congelado, paridade de escala nativa, atalho do cabeçalho de escalas) é
   invisível, mesmo com o modal coberto.
2. **Os toasts viraram o padrão de confirmação da casa** (`addToastRegion` no `ModalEvents.shown`,
   wired em 4 módulos) mas só aparecem em `mod-related`.

## Worklist — 39 arquivos

| Ação | N | Arquivos |
|---|---|---|
| `update-as-is` | 26 | README, form-section, image-dropzone, hierarchy-nav, master-detail, paginated-picker, cohort-assign; screens: est-structure, fwk-frameworks, pln-plans, mod-participants, mod-enrolmethods, mod-delplans, mod-related, mod-links, mod-browser; maps: bar-contextbar, est-structure, fwk-frameworks, pln-plans, mod-participants, mod-enrolmethods, mod-delplans, mod-related, mod-links, mod-browser |
| `update-to-be` | 4 | tokens, states, modal-shell, moodle-ds-alignment |
| `create` | 5 | sticky-footer.html; maps/mod-usage, maps/mod-moveto, maps/mod-structrelated; screens/mod-structrelated |
| `no-change` | 4 | screens/mod-rule, screens/mod-scale, maps/mod-rule, maps/mod-scale — únicos cujas refs de linha ainda resolvem; só spot-check |

## Backlog de código (fora desta rodada)

| # | Item | Estado |
|---|---|---|
| IMP-01 | `context.js` morto no BS4/4.5 | ✅ **Resolvido** em `f84d30a` (v2026071306) |
| IMP-02 | `--bs-modal-width` é BS5-only | ✅ **Resolvido** em `f84d30a` — confirmado real contra v4.5.12; fix por `modal-xl`, mais enxuto que o proposto aqui |
| ✅ | ~~IMP-04: `reloadPane` sem token monotônico → render fora de ordem~~ — **Resolvido** em `8d3c3df` (contador de geração por pane num Symbol; aborta o replace se um reload mais novo bumpou). Sem superfície de kit |
| ✅ | ~~IMP-11: Load de aba que falha não tem caminho de recuperação~~ — **Resolvido** em `c96a3e9` (participants: `startMount` libera o latch no `.catch` → re-clique retenta) + `c2d9471` (enrol: região de erro dedicada). Original: Sem superfície de kit. **Agravado (Task 10, verificado):** no `ensureMounted` do modal de participantes o **latch é permanente** — `mounted = true` roda **antes** do await e o mount nunca é aguardado (`.catch(notifyError)`). Mount que falha deixa o pane **em branco com o latch `true`**: sair e voltar na aba **nunca** retenta, e com `setRemoveOnClose(true)` a única saída é fechar e reabrir o modal. É o caso mais forte para IMP-03+IMP-11 juntos: o spinner mostra que falhou, o refresh deixa tentar |
| ✅ | ~~**Resultados 26+ do picker são inalcançáveis**~~ — **Resolvido** em `b0b3427` (2026-07-16), nos **três** pickers do plugin (competency/course/user), não só o de competências. O alvo do briefing estava errado: `core/form-autocomplete` **não pagina** (reconstrói a lista a cada tecla, sem hook de scroll/load-more), então o fix é o **`data-notice` de overflow** — o `transport` devolve `success(getString('search_toomany'))` quando `total > items.length`, e o `processResults` passa a string com um guard `Array.isArray`. Precedente: `core_user/form_user_selector`. O `total` alimenta a **detecção**, não uma paginação. Sem bump (JS + lang). Kit re-sincronizado em `70ff4fd` (`paginated-picker.html`). Task 17 (original): o picker entre frameworks é um `core/form-autocomplete` fixado em `limitnum: 25`; a WS já devolve o `total` |
| — | **Ilha Material do filtro do aluno reprova em trânsito** | Task 17 mediu: `.local-dimensions-filter-tabs` (`styles.css:3250-3288`) tem **20** tokens em paleta Google Material (`#1a73e8`/`#5f6368`/`#f1f3f4`), não a Boost `#0f6cbf`. O ativo-sobre-indicador passa por 0.01 (4.51:1), mas o **ativo-sobre-plataforma dá 4.05:1** — durante os 320ms de transição, reprova. Sem variante dark. Divergência conhecida, registrada, não corrigida |
| ✅ | ~~**"Adicionar" sem seleção fecha o modal em silêncio**~~ — **Resolvido** em `e14977c` (`competency_browser.js:49` desabilita o save + `:66` `preventDefault`). Original: Task 14: o `if (!calls.length) return` (`competency_browser.js:52-54`) roda **dentro** do handler de save, então não cancela o close do core (`registerCloseOnSave`). Clicar Adicionar sem nada marcado **fecha o modal, não adiciona nada e não avisa**. O `competency_picker` do mtube já guarda esse caso. Fix: `preventDefault()` no evento antes do return |
| ✅ | ~~**Remover o último curso deixa a lista em branco**~~ — **Resolvido** em `5d9da31` (`removeCourse:638`→`refreshListState:448` revela o empty quando `total`=0). Original: Task 13: o `removeCourse` (`competency_links.js:542-562`) apaga o card mas **nunca revela o empty state** — tirando o último curso, a lista fica silenciosamente vazia, sem o "nenhum curso vinculado". Desenhado como as-is na tela; é bug, não decisão |
| ✅ | ~~**`MOD.LINKS-CARD-TOGGLE` sem nome acessível**~~ — **Resolvido** em `7460dcf` (`aria-label` genérico `central_links_toggleactivities`, sem o nome do curso pra não sequestrar clique-por-nome do Behat). Original: Task 13: botão só-ícone com apenas `aria-expanded` — é o único controle do modal nesse estado |
| — | **Trava da aba ≠ trava do link** (participantes) | Task 10: a aba de Métodos de inscrição exige `competency:templatemanage` **no contexto**; o link administrativo dela exige `site:config` **no sistema**. Um gestor de template vê a aba e não vê o link — assimetria não documentada |
| ✅ | ~~**String pt-BR aponta para uma aba que não existe**~~ — **Resolvido** em `7460dcf` (`central_roles_nocohorts` pt-BR: "(aba Públicos-alvo)" → "(aba Coortes)"; a aba é `central_participants_tab_cohorts`="Coortes"). Original: Task 10: `central_roles_nocohorts` manda usar a *"aba Públicos-alvo"*; não existe — a aba é "Coortes". O pt-BR traduz *cohort* como "Público-alvo" só dentro do pane de papéis; o EN é uniforme. Chega ao usuário |
| ✅ | ~~IMP-09: **10** cópias do `flashRow` em 6 módulos + reduced-motion faltando~~ — **Resolvido** em `3c0bf41` (helper único `central/flash.js` com guard de `prefers-reduced-motion`; 14 call-sites; 1500ms = `--mds-motion-flash`) |
| ✅ (parcial) | ~~Empty states: 5+ variantes hand-rolled, sem `role="status"`~~ — **`role="status"` resolvido** em `f73c260` (4 templates + 3 JS-built; os placeholders "nada selecionado" ficaram de fora de propósito). O **partial compartilhado** segue candidato a card futuro (refactor maior, não a11y) |
| ✅ | ~~**Números pré-existentes do `tokens.html` nunca auditados**~~ — **Auditado** em 2026-07-16 contra `theme/boost/scss/preset/default.scss`: os **9 grays batem exato**, as 4 bases de marca (blue `#0f6cbf`, red `#ca3120`, yellow `#f0ad4e`, green `#357a32`) batem exato, todas as conversões rem→px conferem (0.5rem=8px, 0.0625rem=1px, 2.5rem=40px), os tints de feedback são cores reais do Moodle (`#fcefdc` literal em `modules.scss:1283`), e raio/espaço são escalas T-shirt próprias corretamente rotuladas. **Uma divergência achada e corrigida** em `8362597`: o `cyan-600 #006778` do kit não existe no Moodle → alinhado ao `$cyan #008196` real (era entrada de paleta **não consumida**, então sem mudança de render). O resto reproduz — a taxa de erro esperada era não-zero, e foi exatamente um. Provenance em `moodle-ds-alignment.md`; `tokens.html` re-sincronizado no Claude Design. Original: Levantado na Task 1 (2026-07-14). A premissa "todo número do card é real" hoje só vale para as seções Movimento/Carregando, que foram derivadas de comando. O resto veio da Camada 3 e nunca passou por verificação — três dos números do plano já se provaram errados (14→12, 6→10, 764→não reproduz), então a taxa de erro esperada aqui não é zero. Auditoria própria, não escopo desta rodada |
| ✅ | ~~**`.load` no painel escuro: 4.37:1, kit-wide**~~ — **Documentado** em `09388ed` (nota consolidada em `moodle-ds-alignment.md` "Restrições de plataforma": é o `--text-accent` cru do Moodle, depição as-is fiel; superfícies novas usam blue-200 AA-safe; correção de fundo é upstream). Original: Achado na Task 9, e as **três** telas (`est-structure`, `fwk-frameworks`, `pln-plans`) têm a regra **idêntica**: `#3f89cc` sobre `#1d2125` a 11px = 4.37:1, abaixo do AA. É o accent escuro do **próprio Moodle** sobre a superfície do **próprio Moodle**, então não é defeito de uma tela — é decisão de token. Os agentes mantiveram a consistência entre as três em vez de divergir uma, o que é certo. Pertence ao alinhamento de tokens da Camada 3 (README), não a uma tarefa de tela |
| — | **`contrast.js` aconselha mas não bloqueia — e gradua o par errado** | Achado na Task 9: o `contrast.js` (566 linhas) gradua o par de cores do cabeçalho de plano em tempo real, com correções em um clique — mas **aconselha em vez de bloquear**, e o par que ele gradua **o cabeçalho nunca pinta**: os stops renderizados e os chips translúcidos são **derivados** e não são graduados. Os chips de vidro medem **4.22:1** no stop 0, abaixo do AA, sem nada avisando |
| RESOLVIDO | **~~Texto shipado reprova no WCAG AA — 3.11:1~~ — fechado em `84a930d` (2026-07-15)** — e era maior do que o item dizia. Além do `#8b939b` virar `#495057` nos dois pontos, o `opacity: 0.6` do card oculto travava o AA em **qualquer** cor: teto medido de 5,74:1 com preto puro, e o badge Oculta em 2,12:1. Removido — o card já sinaliza com `fa-eye-slash` mais a palavra, então o opacity era sinal redundante. Kit atualizado no mesmo commit. Antigo: | Achado na Task 8, reconferido com a conta: `styles.css:3785` (`fwcard-desc`, a descrição do card de framework) e `:3652` (`fwexcluded`, o contador de ocultos) usam `#8b939b` sobre branco = **3.11:1**, contra os **4.5:1** que o AA exige para texto normal. É **conteúdo**, não decoração. Desvio localizado, não sistêmico: o contador no mesmo arquivo dá 8.18:1. Correção provável: escurecer para o cinza que o resto já usa. A tela `fwk-frameworks` reproduz o defeito de propósito no painel as-is e o registra no mapa |
| ✅ | ~~**pt-BR sem forma singular**~~ — **Já resolvido** (fora desta rodada de auditoria): o pt-BR tem `central_frameworks_hiddencount_one` = "1 oculta" (`lang/pt_br:134`) e `frameworks.php:123-127` escolhe `_one` quando `$excludedcount === 1`. A spec ficou stale. Original: `lang/pt_br:132` tinha `'{$a} ocultas'` sem singular, renderizando "1 ocultas". Achado na Task 8 |
| ✅ | ~~**`makeSpinner()` ship um `role="status"` que se anula**~~ — **Resolvido** em `7460dcf` (forma b do `states.html`: spinner agora decorativo só-`aria-hidden`; o banner do import, que tem o texto, ganha `role="status"`). Original: `amd/src/central/frameworks.js:211-217` põe `role="status"` **e** `aria-hidden="true"` no **mesmo** `<span>`. O `aria-hidden` remove o elemento da árvore de acessibilidade, anulando o role — a live region nunca anuncia, o spinner é anunciado para ninguém. Afeta os 2 sítios do `makeSpinner` (banner do import, botão de export). Correção: ou tirar o `aria-hidden` e pôr texto `visually-hidden` dentro (padrão do Bootstrap), ou tirar o `role` e deixar o spinner decorativo, com o container anunciando. O `states.html` (Task 2) já desenha a segunda forma, que é a certa — o código é que diverge do card |
| ✅ | ~~**`--mds-bg-interactive-secondary-*` quebra no dark**~~ — **Resolvido** em `683157a` (2026-07-16). Fix em duas partes: (1) override dark `secondary-default:#343a40`/`-hover:#495057` no bloco `prefers-color-scheme:dark` (segue o `hierarchy-nav.html`); (2) o swatch `.btn-sec` passou a consumir o token interativo real (usava `surface-default`). Medido no DOM: **12.44:1** claro / **10.91:1** dark (o par pré-fix reproduziu **1.24:1** exato). Kit-only (zero no `styles.css`), sem runtime, sem bump; `tokens.html` sincronizado no Claude Design. Original: Verificado 2026-07-14: o bloco `prefers-color-scheme:dark` do `tokens.html` sobrescreve superfície, texto, borda e feedback — e **zero** dos 9 tokens interativos. Para `primary`/`danger` tudo bem (fills sólidos saturados com texto branco funcionam nos dois esquemas). Mas `secondary-default`/`-hover` são gray-300/400, cinzas **claros** para texto escuro; no dark, com `--mds-text-default` em `#f8f9fa`, é branco sobre cinza claro. Descoberto porque a Task 1 precisou de um par de hover dark-aware e teve de **rejeitar o par semanticamente óbvio** — o sintoma já custou uma decisão de design. O card não expõe o bug hoje só porque o swatch `.btn-sec` usa `--mds-bg-surface-default`, não o token interativo que diz demonstrar |

## Critérios de sucesso

1. Toda tela/mapa as-is bate com o código shipado em 2026-07-14 (refs de linha resolvem).
2. Todo painel to-be consome o vocabulário da Seção 1 e marca a origem mtube (D4).
3. As 5 superfícies hoje sem cobertura (`mod-usage`, `mod-moveto`, `mod-structrelated`,
   sticky-footer) têm mapa e/ou tela.
4. O invariante do sticky-footer está escrito no card, não só na memória.
5. O projeto Claude Design reflete o repo (mesmos `.html`, `maps/` só local).
6. Nenhuma proposta to-be viola o boundary do CI (`!important`, `clamp()`/`min()`/`max()` em
   altura) nem a regra BS4/BS5.
