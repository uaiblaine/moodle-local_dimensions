# Design — toggle "Mostrar categorias ocultas" na contextbar (`BAR-CATHIDDEN`)

Data: 2026-07-18 · Status: **design aprovado, implementação pendente** (o usuário separou
desenho de código; este doc é o desenho).

Fecha o item 3 do backlog da Central: o irmão adiado do "Mostrar frameworks ocultos", agora
para **categorias de curso** no seletor de contexto. Ver `docs/design-kit/screens/bar-contextbar.html`
(as-is → to-be) e `docs/design-kit/maps/bar-contextbar.md` (linha de controle `BAR-CATHIDDEN`).

## Problema

A contextbar lista categorias via `helper::central_category_options()` →
`core_course_category::make_categories_list()`. Uma categoria `visible=0` só entra na lista
para quem tem `moodle/category:viewhiddencategories`; quando entra, **não há como escondê-la**.
Não existe controle de categorias ocultas na barra. (O "Mostrar estruturas ocultas" existe,
mas é da aba Estruturas e trata de **frameworks**, não de categorias.)

## Decisões (travadas com o usuário)

1. **Semântica** — paridade com "Mostrar frameworks ocultos": por padrão o picker mostra só
   categorias visíveis; o toggle revela as categorias `visible=0` **que o usuário já tem
   permissão de ver**. Quem não tem a capability nunca vê ocultas → o toggle **não é
   renderizado** (sem controle morto).
2. **Posição** — bloco próprio **logo após o select de categoria**, alinhado pela base, dentro
   do grupo que some no modo Sistema; quebra para a linha de baixo em tela estreita
   (`flex-wrap`).
3. **Persistência** — **por-usuário**, na pref JSON `central_nav` (que já guarda contexto +
   categoria), como chave `showhiddencats`. Sobrevive sessões e dispositivos.
4. **Forma** — pílula-switch do kit (`.tgl`/`.tgl-track`), `<label for>` **real** (o named
   selector "checkbox" do Behat não casa por `aria-label`).
5. **Comportamento** — client-side, **sem `reloadPane`** (espelha `applyShowHidden` da aba
   Estruturas): o servidor renderiza todas as opções (visíveis + ocultas-permitidas) marcando
   as ocultas com `data-hidden="1"`; o `<select>` mostra só as visíveis por padrão; ligar o
   toggle reconstrói a lista de `<option>` a partir de um snapshot, preservando a seleção. Só a
   **lista** muda — o contador da barra é independente (conta estruturas/planos visíveis do
   contexto, não categorias).
6. **Edge** — se a categoria selecionada persistida for oculta, o toggle **inicia ligado**
   (senão o contexto atual sumiria da lista).

## Mudanças de código previstas (para a fatia de implementação)

- **`classes/helper.php`**
  - `central_category_options()`: marcar cada opção com `hidden` (bool) lendo o `visible` da
    categoria (`core_course_category` já cacheado em MUC; ou um `get_records_list` em
    `{course_categories}` para os ids da lista). `make_categories_list()` já inclui as ocultas
    para quem tem a capability — só falta o flag.
  - `get_central_prefs()`: sanitizar a chave nova `showhiddencats` (bool) na seção `central_nav`
    (toda chave nova precisa entrar no sanitizador, senão o seed volta ao default).
- **`classes/output/central/contextbar.php`**: expor `hashiddencategories` (alguma opção
  oculta) para o gate de render; semear o estado inicial do toggle a partir da pref +
  do edge (categoria selecionada oculta).
- **`templates/central/contextbar.mustache`**: `data-hidden="1"` nas options ocultas; bloco do
  toggle após `data-region="category-wrapper"` (dentro do gate de `iscoursecat` e de
  `hashiddencategories`), com `<label for>` real.
- **`amd/src/central/context.js`**: no `init`, ler `showhiddencats` (via `preferences.js`) +
  aplicar o edge; `applyShowHiddenCats()` reconstrói o `<select>` a partir de um snapshot das
  options (mirror de `applyShowHidden`), preservando a seleção; `change` do toggle grava na
  pref (debounced) e reconstrói. Rebuild + rebuild após troca de contexto (o wrapper já é
  clonado como `pristineCategoryNode`).
- **`amd/src/central/preferences.js`**: incluir `showhiddencats` no shape de `central_nav`
  (getter/setter de nav).
- **Lang** `lang/en` + `lang/pt_br`: string nova `central_bar_showhiddencategories` (slot
  alfabético em ambos). Privacidade **sem** string nova (a pref `central_nav` já é exportada).
- **`version.php`**: bump conforme a política do freeze (muda mustache/JS servidos + shape da
  pref); rebuild do AMD (`npx grunt amd --root=public/local/dimensions`).

## Fora de escopo / não fazer

- Não tocar o **contador** da barra (D5: conta o contexto; ver o mapa). O toggle não muda o
  número.
- Não usar `sessionStorage` (é o mecanismo do irmão de frameworks; aqui a decisão foi pref
  por-usuário para consistência com o resto do estado da barra).
- Sem WS nova.

## Verificação (na implementação)

- Behat: como é `<label for>` real, o named selector "checkbox" casa; smoke render-only
  (o gotcha de corrida do JS init em dynamic_tab lazy vale — manter render-only).
- PHPUnit: `central_category_options()` marca `hidden` corretamente (categoria visível vs
  oculta com a capability); sanitizador de `central_nav` preserva `showhiddencats`.
- Runtime (site do usuário): revelar/esconder preserva a seleção; edge da categoria oculta
  selecionada inicia ligado; persistência entre sessões; perna 4.05/BS4.
