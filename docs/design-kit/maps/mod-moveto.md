# Mapa de Campos — `MOD.MOVETO` · Mover para posição (as-is)

Modal de reordenação: um `<select>` numerado com **uma opção por posição**, cada uma anotada com a
competência que hoje ocupa aquele lugar. Salvar move. É a **alternativa de teclado ao arrasto** — o
grip é ponteiro-puro — e o caminho prático quando a lista é longa demais para arrastar.

**Um template, dois chamadores, duas estratégias de persistência diferentes.** A aba Planos reordena
competências dentro de um modelo com **uma** chamada de reorder do core; a aba Estrutura reordena nós
irmãos da árvore **empilhando |delta| movimentos de um passo**. O corpo é idêntico; o que acontece no
save não é. Esse contraste é o motivo deste mapa existir.

- **Mustache:** [`move_competency_modal.mustache`](../../../templates/central/move_competency_modal.mustache)
  (45) — só o **corpo**; o `ModalSaveCancel.create` é em JS, dos dois lados
- **AMD (dois chamadores):**
  - [`plans.js`](../../../amd/src/central/plans.js) — `moveCompetencyTo` em `:548-606`; despacho em
    `:753`. Helpers: `refreshMoveState` (`:128-140`), `flashRow` (`:115`), `reloadKeepingScroll` (`:92`)
  - [`structure.js`](../../../amd/src/central/structure.js) — `openNodeMoveModal` em `:972-1007`;
    `persistNodeMove` em `:941-961`; `nodeSiblings` em `:928`. Duas portas: rodapé
    (`:1278-1282`) e grip (`:1373-1381`)
  - Ambos importam `core/modal_save_cancel` e `core/modal_events`
- **WS:** **nenhum do plugin** — só core, e **diferente em cada aba**:
  - Planos: `core_competency_reorder_template_competency` (`plans.js:581-587`) — **uma** chamada
  - Estrutura: `core_competency_move_up_competency` / `core_competency_move_down_competency`
    (`structure.js:947-951`) — **|delta| chamadas** em `Promise.all`
- **CSS:** **nenhum.** Um `grep -n 'plans-move' styles.css` não devolve nada — a classe do `:36` é só
  gancho semântico. O corpo é Bootstrap puro (`form-select`, `d-block small text-muted mb-1`)
- **Behat:** nenhum. O `CLAUDE.md` desaconselha Behat de drag-and-drop; o **modal**, que é a porta de
  teclado, também não tem cobertura — ver a nota de cobertura
- **Tela no DS:** nenhuma. É um `<label>` + um `<select>`; a decisão de design está nas **regras**,
  não no desenho

**Abreviações usadas nas tabelas:** `mustache:` = `templates/central/move_competency_modal.mustache`
· `plans.js:` = `amd/src/central/plans.js` · `structure.js:` = `amd/src/central/structure.js`.
Caminhos que começam com `lib/` são do **core**, relativos a `public/`.

> **Mapa novo (2026-07-15) — a superfície não tinha mapa, e duas referências apontavam para o vazio.**
>
> - **`MOD.MOVETO` era um destino inexistente.** Um `grep -rn 'MOD\.MOVETO' docs/design-kit/` devolvia
>   **11 ocorrências, em 11 linhas de 4 arquivos** — `est-structure.md` (3: `:140`, `:149`, `:150`),
>   `pln-plans.md` (6: `:186`, `:205`, `:233`, `:255`, `:266`, `:341`), `screens/est-structure.html`
>   (`:249`) e `screens/pln-plans.html` (`:554`) — e **nenhum arquivo `mod-moveto.md`** para onde elas
>   apontassem. As aposentadorias da Task 7 (`EST-DETAIL-MOVEUP` / `EST-DETAIL-MOVEDOWN`,
>   `est-structure.md:149-150`) redirecionavam o leitor para um mapa que não existia. Este arquivo é o
>   destino; o `est-structure.md` ganhou o link.
> - **O brief errou as linhas do `plans.js` — para menos.** Ele dizia `:558-573`. Esse intervalo é só o
>   miolo (montar as opções + criar o modal); a função `moveCompetencyTo` vai de **`:548` a `:606`**, e
>   tudo o que **importa** — a chamada do WS (`:581-587`), o reposicionamento espelhado (`:588-595`) e o
>   rollback (`:599-604`) — cai **fora** do intervalo do brief. As linhas do `structure.js` (`:972-1007`)
>   estavam **exatas**.
> - **O grip da Estrutura abre o modal, e não por `data-action`.** O `structure_node.mustache:111-116`
>   carrega **só** `data-region="node-drag-handle"` — nenhum `data-action`. Quem o transforma em porta é
>   um galho dedicado do listener da região (`structure.js:1373-1381`), não o despacho por ação. O
>   `est-structure.md:91` descrevia o `EST-NODE-DRAG` só como arrasto; ganhou a menção.

## Gatilhos (fora do modal) — **quatro portas, nenhuma nova aqui**

Todas as portas já têm ID nos mapas das abas. Este mapa **as referencia**.

| ID (dono) | Aba | Origem | Mecanismo | Regra |
| --- | --- | --- | --- | --- |
| `PLN-COMP-MOVETO` | Planos ([`pln-plans.md`](pln-plans.md)) | `plans.mustache:423-426` | `data-action="move-competency-to"` → `ACTION_HANDLERS` (`plans.js:753`) | item do kebab; ícone `fa-arrows-v` |
| `PLN-COMP-GRIP` | Planos ([`pln-plans.md`](pln-plans.md)) | `plans.mustache:440-445` | `data-action="move-competency-to"` **e** `data-region="drag-handle"` | **acumula as duas funções**: clicar abre o modal, arrastar reordena direto |
| `EST-DETAIL-MOVETO` | Estrutura ([`est-structure.md`](est-structure.md)) | `structure_footer_actions.mustache:61-64` | `data-action="moveto"` → `handleDetailAction` (`structure.js:1278-1282`) | botão do **sticky-footer**; age na linha ativa do módulo |
| `EST-NODE-DRAG` | Estrutura ([`est-structure.md`](est-structure.md)) | `structure_node.mustache:111-116` | **`data-region="node-drag-handle"`** → galho próprio (`structure.js:1373-1381`) | **sem `data-action`** — a porta é o `closest()` do listener da região, com `preventDefault()` (`:1375`) para o clique não selecionar a linha |

**Os dois grips prometem a mesma coisa e cumprem.** Ambos têm `title` **e** `aria-label` =
`central_plans_moveto` + `': '` + shortname (`plans.mustache:442-443`,
`structure_node.mustache:113-114`), e ambos abrem o modal no clique — por caminhos diferentes. É o
que mantém o rótulo honesto para teclado: o arrasto é ponteiro-puro, o clique não.

## Corpo (o mesmo nos dois chamadores)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.MOVETO-TITLE` | Mover para posição… | título | `plans.js:569` · `structure.js:988` | str `central_plans_moveto` | string **crua**, sem `$a`: **não nomeia o alvo**. Aberto pelo grip da linha "Comunicação", o título não diz "Comunicação" — quem carrega o nome é a opção marcada do select |
| `MOD.MOVETO-MODAL` | — | `core/modal_save_cancel` | `plans.js:568-573` · `structure.js:987-992` | `show: true`, `removeOnClose: true` | **sem `large`** (ao contrário do `MOD.USAGE`/`MOD.DETAIL`): é um campo só. O rodapé Salvar/Cancelar vem de graça; nenhum dos dois chama `setSaveButtonText` |
| `MOD.MOVETO-ROOT` | `[sem rótulo]` | região/raiz | `mustache:36` | `.local-dimensions-central-plans-move` | **sem CSS** |
| `MOD.MOVETO-LABEL` | Nova posição | rótulo | `mustache:37-39` | str `central_plans_moveto_label` · `for` | `<label>` **de verdade**, com `for` casando o `id` do select — o único modal do kit cujo rótulo é um `label` ligado (o `MOD.RELATED-ADDLABEL` é uma `div`, porque lá o alvo é uma árvore) |
| `MOD.MOVETO-SELECT` | — | select | `mustache:40-44` | `.form-select` · `id` = `name` = `local-dimensions-plans-move-position` | `form-select`, **nunca `custom-select`** (regra do `CLAUDE.md`). O `id` é **fixo, não `uniqid`** — só existe um destes por vez porque o modal é `removeOnClose`. Lido por `querySelector` no save, dos dois lados (`plans.js:575`, `structure.js:994`) |
| `MOD.MOVETO-OPTION` | {n}. {nome} | option | `mustache:42` | `value` = índice **base 0** · `selected` | o rótulo é **1-based** (`(index + 1) + '. ' + nome`) e o `value` **0-based** — `plans.js:563`, `structure.js:982`. A opção da posição atual nasce `selected` (`plans.js:564`, `structure.js:983`) |
| `MOD.MOVETO-SAVE` | Salvar mudanças | botão (rodapé) | `lib/templates/modal_save_cancel.mustache:44` | `data-action="save"` | str core `savechanges`. **Único ponto de escrita** — `ModalEvents.save` (`plans.js:574`, `structure.js:993`) |
| `MOD.MOVETO-CANCEL` | Cancelar | botão (rodapé) | `lib/templates/modal_save_cancel.mustache:43` | `data-action="cancel"` | str core `cancel`. Cancelar, X ou ESC: **nada é gravado** — não há handler de `hidden` em nenhum dos dois |

## Os dois chamadores, lado a lado

| | **Planos** (`plans.js:548-606`) | **Estrutura** (`structure.js:972-1007`) |
| --- | --- | --- |
| **Universo** | `[data-competency]` dentro de `[data-region="competency-items"]` (`:549`, `:554`) — a lista **plana** do modelo | `nodeSiblings(node)` (`:973`) — os irmãos **de mesmo pai** na árvore (`:928`: filhos do `parentElement` que casam `.local-dimensions-central-node`) |
| **Desiste quando** | `rows.length < 2` (`:555-557`) | `siblings.length < 2` (`:974-976`) |
| **Rótulo da opção** | `textContent` do `PLN-COMP-NAME` (`:560-563`) — lê a **tela** | `row.dataset.name` (`:979-982`) — lê o **dataset** |
| **Escrita** | **1** × `core_competency_reorder_template_competency` (`:581-587`), com `competencyidfrom`/`competencyidto` e o `templateid` do `pane.dataset` (`:584`) | **|delta|** × `move_up`/`move_down` (`:947-951`), montadas por `Array.from({length: Math.abs(delta)})` e disparadas em `Promise.all` (`:952`) |
| **Ordem** | **WS primeiro, DOM depois** (`:588-595`): o `.then` reposiciona | **DOM primeiro** (`:1000-1004`), `persistNodeMove` depois (`:1005`) |
| **Confirmação** | `refreshMoveState(list)` + `flashRow(row)` (`:596-597`) | `row.animate([…#fff3cd…], {duration: 1500})` dentro do `persistNodeMove` (`:953`) |
| **Rollback** | `reloadKeepingScroll(pane)` (`:603`) | `reloadPane(pane)` (`:959`) |
| **Sem-op** | `targetindex === current` → `return` (`:577-579`) | idem (`:996-998`), **mais** o `if (!delta) return` do `persistNodeMove` (`:942-945`) |

## Regras de negócio (verificadas no código)

### 1. O espelhamento da semântica do core — e por que só a aba Planos precisa dele

O comentário do `plans.js:589-590` é a chave: *"Core lands the row **after** the occupant when moving
down, **before** it when moving up"*. Daí o par `reference.after(row)` / `reference.before(row)`
(`:591-595`): o DOM imita o que o servidor acabou de fazer, e a lista fica certa **sem reload**.

O `structure.js:1000-1004` tem o **mesmo** par `after`/`before` — mas por um motivo diferente. Ali o
DOM se move **antes** de qualquer chamada, e a persistência é uma pilha de passos unitários
(`move_up`/`move_down`), que **não têm ambiguidade de destino**: cada uma troca com o vizinho. O
`after`/`before` da Estrutura não espelha semântica do core; ele apenas coloca o nó no índice que o
usuário escolheu, e o `persistNodeMove` conta quantos passos aquilo custou (`delta = to - from`,
`:942`).

**A consequência prática:** um salto de 12 posições na aba Planos é **1** request; na Estrutura são
**12**, em paralelo. O `Promise.all` (`:952`) não garante ordem de chegada, mas cada `move_up`/
`move_down` é relativa à posição corrente no servidor — e o core as serializa. É por isso que a
falha derruba tudo para um `reloadPane` (`:955-960`): a única verdade recuperável é a do servidor.

### 2. `move_competency_modal` é do Planos só no nome

O template diz, no próprio docblock (`:20`), *"Body of the 'move competency to position' modal **on
the Plans tab**"*. Não é mais verdade desde que a Estrutura passou a usá-lo (`structure.js:986`). E o
nome vazou para todo lado:

- o `id`/`name` do select é **`local-dimensions-plans-move-position`** (`mustache:40`) — na árvore de
  competências também;
- as duas strings são **`central_plans_moveto`** e **`central_plans_moveto_label`**;
- a classe da raiz é **`.local-dimensions-central-plans-move`** (`mustache:36`).

Nada disso quebra — o select é lido por `querySelector` dentro da raiz do próprio modal
(`structure.js:994`), então o `id` só precisa ser único **naquele** modal, e ele é (`removeOnClose`).
Fica registrado como **verruga de nomenclatura**, não como bug: qualquer renomeação tem de mexer nos
dois módulos, no template e nas duas línguas de uma vez.

### 3. O modal não sabe se a posição ainda existe

As opções são um retrato do DOM no instante da abertura (`plans.js:554`, `structure.js:973`). O save
revalida **só** o índice contra o array capturado — `!rows[targetindex]` (`:577`),
`!siblings[targetindex]` (`:996`) —, nunca contra o servidor. Como os dois arrays são capturados na
mesma função e o modal é modal (bloqueia a aba atrás), a janela é estreita; mas **outra sessão**
reordenando a mesma lista faz o índice significar outra coisa. O core resolve pelo id
(`competencyidfrom`/`competencyidto`), então o resultado é um move para o lugar **errado**, não um
erro. Sem cobertura de teste.

### 4. `refreshMoveState` só existe do lado dos Planos

Depois de um reorder in-place, os itens "Mover para cima"/"Mover para baixo" do kebab de **cada**
linha precisam recalcular seu `disabled` (o primeiro não sobe, o último não desce) —
`refreshMoveState(list)` (`plans.js:128-140`) varre as linhas e reajusta os dois botões.

A Estrutura **não tem** esse par de botões: `EST-DETAIL-MOVEUP` e `EST-DETAIL-MOVEDOWN` foram
**aposentados** (ver `est-structure.md:149-150`) e viraram este modal + o arrasto. Por isso o
`persistNodeMove` não chama nada equivalente — não há estado de borda para recalcular. As duas
setas viraram uma porta só, e é esta.

### 5. Cobertura: a porta de teclado não é testada

Não há `.feature` tocando o `MOD.MOVETO` — nem pelo grip, nem pelo rodapé, nem pelo kebab. O
`CLAUDE.md` desaconselha Behat de arrasto (frágil em headless), e a orientação foi seguida; mas o
**modal** é justamente a alternativa determinística ao arrasto — um `select` e um botão Salvar, sem
nada de frágil. É a lacuna mais barata de fechar do kit: abrir pelo kebab (`PLN-COMP-MOVETO`, já
dentro de um dropdown — abrir o ⋯ primeiro, per o `CLAUDE.md`), `I set the field "Nova posição" to
"2. …"`, salvar, conferir a ordem.
