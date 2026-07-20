# Central admin kit — alinhamento ao mtube · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deixar o design kit da Central (`docs/design-kit/`, espelhado no projeto Claude Design `35784af0-29b9-434f-b3f0-9618fa749829`) fiel ao código shipado em `f84d30a` / v2026071306, e mapear as melhorias inspiradas no `modal=course-report` do `course/format/mtube`.

**Architecture:** Abordagem A do spec — **fundações primeiro** (tokens de movimento, estado busy, shell de modal to-be, card do sticky-footer), depois **varredura por superfície** com mapa e tela juntos, depois as criações, depois o sync. Cada `.html` é um preview self-contained (tokens inline, claro/escuro via `prefers-color-scheme`, marcador `@dsCard` na linha 1). Os `maps/*.md` ficam só no repo; os `.html` sobem como cards.

**Tech Stack:** HTML/CSS estáticos (sem build, sem JS), Markdown, ferramenta `DesignSync`. Nenhum código de plugin muda.

**Spec:** `docs/superpowers/specs/2026-07-14-central-mtube-alignment-design.md`

---

## Como este plano difere de um plano de código

Não há TDD aqui: os arquivos do kit são previews estáticos, sem suíte de testes. O ciclo de verificação equivalente, obrigatório em toda tarefa de tela/mapa, é:

1. **Derivar a verdade do código primeiro** (grep no mustache/js), antes de escrever o painel.
2. **Escrever/atualizar** mapa e tela.
3. **Verificar que as referências resolvem** — todo `arquivo:linha` do mapa tem que apontar pro que ele diz que aponta, em `f84d30a`.

O passo 1 antes do 2 é o que impede o kit de virar ficção. Um painel as-is escrito de memória é pior que nenhum painel, porque é lido como verdade.

## Restrições que valem em toda tarefa

- **Um card não pode afirmar o que ele não faz.** Este é o defeito recorrente desta rodada — pego
  três vezes, em três fantasias: (1) `transition:` em swatch sem `:hover` = transição inerte;
  (2) anel de spinner sem `@keyframes` = spinner congelado; (3) legenda dizendo `aria-busy="true"`
  num elemento sem o atributo. Se o card **diz** que demonstra algo, ele demonstra — ou não diz.
  Vale para atributo ARIA, estado, movimento e número. Antes de commitar, releia cada afirmação do
  card e pergunte "o markup ao lado sustenta isto?".
- **O card especifica o padrão do HUB. A comparação com o mtube mora na spec, não no card.**
  Regra criada na Task 2 depois de **três** rodadas de retrabalho, com um padrão inequívoco: toda
  afirmação sobre o **hub** saiu exata de primeira (`finally`=5, os 4 sítios de spinner, os
  contrastes, a fidelidade byte-a-byte ao Bootstrap). **Todo** o retrabalho veio de afirmações
  sobre o **mtube** — auditar outra base é caro, e o erro se disfarça: o número sai exato e a frase
  que ele sustenta sai falsa (17 arquivos → "o JS do mtube"; depois a contagem certa → a camada
  errada; depois `role=status` atribuído a um template que não tem `role`).
  Se você **precisar** citar o mtube num card, então: (a) abra o código que a frase descreve — não
  agregue por camada/diretório e conclua sobre um caminho específico; (b) confira
  atributo-a-atributo, não "de memória do que é o padrão"; (c) escreva o escopo na própria frase.
  Na dúvida, **não cite** — a spec já carrega a justificativa comparativa e ninguém copia a spec
  achando que é o padrão.
- **Medir contraste contra o ancestral PINTADO, não contra o pai.** Task 16: o pai do alerta é
  `rgba(0,0,0,0)`, então a medição contra ele deu **18.22:1** — número lindo e falso; contra o
  ancestral realmente pintado, **1.15:1**. Suba a árvore até achar quem pinta.
- **Medir cor num preview: cancele as animações antes.** Achado na Task 13, depois de diagnosticar
  errado duas vezes: uma `CSSTransition` em `color` fica **congelada em `currentTime: 0`** porque a
  aba do preview nunca pinta — e aí o `getComputedStyle` devolve a cor do **tema anterior**,
  reportando falha de contraste que não existe (1.49:1 falso, real 4.83:1). Esperar não resolve;
  injetar `transition:none` não resolve. Só `getAnimations().forEach(a => a.cancel())` assenta.
- **Verificar movimento/animação: cuidado com falso negativo por throttling.** Aba de fundo não
  pinta, `rAF` não dispara e `document.timeline` estagna — o transform fica na identidade e
  `playState` diz `running`. Isso é **idêntico** a um spinner morto. Ler o CSS aprova errado;
  amostrar ingenuamente reprova errado. Prove forçando a timeline (seek para 25%/50% e conferir
  `matrix(0,1,-1,0,0,0)` = 90°, `matrix(-1,0,0,-1,0,0)` = 180°) ou rode num renderer que pinta de
  fato. Duas tarefas já perderam tempo nisto.
- **Uma busca que FALHA não é evidência de ausência.** Causou dois defeitos nesta rodada, os dois
  com a mesma assinatura: `grep amd/src/` no mtube devolve "No such file or directory" (o mtube só
  tem `amd/build`), e disso se concluiu "o JS do mtube é inauditável" (Task 2) e "o mtube não põe
  links no rodapé" (Task 3). **As duas conclusões eram falsas** — o fonte original está nos
  `.min.js.map` (`jq -r '.sourcesContent[0]'`), e os maps ficam em `amd/build/features/`, então um
  glob em `amd/build/*.map` acha 17 de 79. Antes de concluir "não existe", confirme que **procurou
  onde a coisa estaria**. Erro de caminho, glob estreito e diretório inexistente são todos
  indistinguíveis de "zero resultados" — e nenhum deles é uma medição.
- **Citar o mtube por NOME DE SÍMBOLO, nunca por `arquivo:linha`.** Verificado na Task 10: o
  `format_mtube` **não tem `amd/src`** e é *untracked* neste checkout (`git ls-files` → 0). O fonte
  só existe dentro do `sourcesContent` dos `.map`, então um `arquivo:linha` de JS do mtube **não
  resolve para ninguém** — este plano já citou `modal_fullscreen.js:29`, que é linha em branco num
  arquivo inexistente. Cite `renderFullscreenButtons`/`setModalFullscreen` (verificável dos dois
  lados); `arquivo:linha` só para o CSS dele, que é arquivo de verdade.
- **Nenhum número entra num arquivo do kit sem sair de um comando que você rodou nesta tarefa.**
  Se o plano cita um número e o seu comando devolve outro, **o código vence** — corrija o texto e
  relate a divergência. Se um número do plano **não reproduz por nenhum método**, não o escreva:
  troque por prosa ("todo o CSS shipado"), porque um número que ninguém reproduz é pior que nenhum.
  Os números deste plano vieram de uma auditoria e **não são todos confiáveis** — a Task 1 já pegou
  três errados ("14 durações" → 12, "6 cópias" → 10, "764 blocos" → não reproduz). Trate todo
  número aqui como hipótese a verificar, não como fato. Isto vale em dobro para os cards, cuja
  premissa é que o que está escrito neles é real.
- **Badges de origem em pt-BR** (decidido 2026-07-14, depois de a Task 7 flagrar a divergência):
  `mtube: atualizar`, `mtube: expandir`, `mtube: carregando`, `mtube: ícones nas abas`,
  `mtube: links no rodapé`. O kit é pt-BR e o badge é prosa que o leitor lê — não misture registro.
  Já normalizado em `modal-shell.html`, `maps/bar-contextbar.md`, `maps/est-structure.md` e
  `screens/est-structure.html`; use estes termos nas tarefas restantes.
- **Marcador `@dsCard` na linha 1** de todo `.html`, formato exato:
  `<!-- @dsCard group="…" name="…" subtitle="…" -->`
- **Self-contained**: tokens inline no `<style>`, claro/escuro via `@media (prefers-color-scheme:dark)`. Sem `<link>`, sem CDN, sem fonte remota.
- **Nunca escrever marcador de leftover literal** (to-do, fixme, marcador de conflito) em nenhum arquivo — o checker do CI reprova em qualquer arquivo, docs incluídos.
- **Nenhuma proposta to-be pode violar o CI**: sem `!important`; sem `clamp()`/`min()`/`max()` em propriedades de altura (`csstree/validator`); `calc()` é aceito.
- **Invariante do sticky-footer**: nenhum to-be remove botão do sticky-footer da página — eles lançam ~10 dos 17 modais.
- **Fronteira do kebab (decidida 2026-07-14)**: a regra "sem kebab por linha" governa o CRUD da *entidade da aba* (framework / nó / template). O kebab de competências dentro da lista de um plano (`plans.mustache:396-430`) está **correto** e não se mexe.
- **Commits**: um por tarefa, em português no corpo se preciso, mas **assunto em inglês** (convenção do repo). Só `docs/design-kit/` é versionado (`.gitignore` libera só ele dentro de `docs/`).
- **Não fazer push** sem comando explícito do usuário.

---

## File Structure

| Arquivo | Responsabilidade | Fase |
|---|---|---|
| `tokens.html` | Fundações: cor, raio, traço, foco, sombra, espaçamento, tipografia **+ movimento (novo)** | 1 |
| `states.html` | Estados interativos + foco **+ busy/loading (novo)** | 1 |
| `modal-shell.html` | Shell de modal: cabeçalho/corpo/rodapé — **to-be acoplado (D2)** | 1 |
| `sticky-footer.html` | **Novo.** As 3 variantes reais + o invariante escrito | 1 |
| `moodle-ds-alignment.md` | Alinhamento MDS **+ restrições de plataforma (novo)** | 1 |
| `screens/*.html` (12) | as-is ↔ to-be por superfície, com IDs | 2-3 |
| `maps/*.md` (15) | Inventário as-is por superfície, com origem `arquivo:linha` | 2-3 |
| `README.md` | Índice + as duas lacunas conhecidas | 4 |

---

# FASE 1 — Fundações

### Task 1: `tokens.html` — seção Movimento

**Files:**
- Modify: `docs/design-kit/tokens.html` (bloco `:root` na linha 3-25; nova seção antes do `.callout` final)

- [ ] **Step 1: Confirmar a realidade que os tokens governam**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
# Quantas durações distintas existem hoje (esperado: ~14, sem vocabulário)
grep -oE 'transition:[^;]*' styles.css | grep -oE '[0-9.]+m?s' | sort -u
# O flash duplicado (esperado: 6 ocorrências)
grep -rn "backgroundColor: '#fff3cd'" amd/src/ | wc -l
# Os 2 blocos de reduced-motion existentes
grep -n 'prefers-reduced-motion' styles.css
```

Anote os números reais — eles entram no texto do card. Se divergirem do spec, o **código** vence.

- [ ] **Step 2: Adicionar os tokens de movimento ao `:root`**

No bloco `:root` (após a linha do `--mds-shadow-*`), adicionar:

```css
  --mds-motion-fast:150ms;--mds-motion-base:250ms;--mds-motion-flash:1500ms;
  --mds-motion-ease:cubic-bezier(0.4, 0, 0.2, 1);
  --mds-loading-min-height:12rem;
```

- [ ] **Step 3: Adicionar a seção visual "Movimento"**

Inserir antes do `<div class="callout">` final:

```html
<div class="h">Movimento (o kit não tinha vocabulário; o código tem 12 durações soltas)</div>
<div class="row">
  <span class="fb" style="border-color:var(--mds-border-default);transition:background-color var(--mds-motion-fast) var(--mds-motion-ease)">fast 150ms · hover, foco, cor</span>
  <span class="fb" style="border-color:var(--mds-border-default);transition:background-color var(--mds-motion-base) var(--mds-motion-ease)">base 250ms · layout, indicador de aba</span>
  <span class="fb" style="border-color:var(--mds-border-default)">flash 1500ms · confirmação in-place</span>
</div>
<div class="note" style="margin-top:6px;">
  easing único <code>--mds-motion-ease</code> = <code>cubic-bezier(0.4, 0, 0.2, 1)</code> (Material standard, já usado no FAB).
  <code>fast</code> bate com o <code>.15s</code> do Bootstrap; <code>flash</code> é o valor já shipado.
</div>
<div class="h">Carregando</div>
<div class="note">
  Sem token de spinner: usamos o <code>spinner-border</code> do Bootstrap, que traz o próprio
  <code>.75s linear infinite</code> — nenhuma keyframe para escrever e nenhuma dívida de reduced-motion.
  Entra só <code>--mds-loading-min-height: 12rem</code>, para o pane não colapsar enquanto carrega.
</div>
```

- [ ] **Step 4: Adicionar o callout de honestidade**

Dentro do `<ul>` do `.callout` existente, acrescentar dois `<li>`:

```html
    <li><b>Movimento</b>: <code>--mds-motion-flash</code> é consumido por <b>JS (WAAPI)</b>, não por CSS — é a
        referência para deduplicar as <b>10 cópias</b> do <code>flashRow</code> num helper só.</li>
    <li>Estes tokens são consumidos por <b>zero</b> regras shipadas hoje. Escopo de adoção: superfícies
        <b>novas ou reformuladas</b> — retro-tokenizar todo o CSS shipado briga com a regra de menos código.</li>
```

- [ ] **Step 5: Verificar o preview**

```bash
head -1 docs/design-kit/tokens.html   # @dsCard intacto na linha 1
grep -c 'prefers-color-scheme' docs/design-kit/tokens.html   # >= 1
grep -nE '[T]ODO|FIXME|<<<<<<<' docs/design-kit/tokens.html || echo "OK sem leftover"
```

Abrir o arquivo no navegador e conferir claro **e** escuro (as novas linhas não podem depender de token que só existe no claro).

- [ ] **Step 6: Commit**

```bash
git add docs/design-kit/tokens.html
git commit -m "docs(kit): name the hub's motion in tokens

The kit specified colour, radius, stroke, focus, shadow, spacing and type but
never motion, so it could not back a loading state or a named duration. The
shipped CSS has 14 distinct transition durations with no shared curve — three
of them for the same hover intent.

Adds fast/base/flash plus one easing, each anchored to something real rather
than invented: fast matches Bootstrap's own .15s, flash is the duration the six
flashRow copies already use. Records two honesties on the card: the flash token
is read by JS, not CSS, and nothing shipped consumes any of these yet."
```

---

### Task 2: `states.html` — estado busy/loading

**Files:**
- Modify: `docs/design-kit/states.html`

- [ ] **Step 1: Confirmar os três tratamentos one-off que existem hoje**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
grep -rn 'spinner-border\|fa-spinner\|fa-spin' amd/src/ templates/ | head
```

Esperado: o banner do import (`alert alert-info` + `spinner-border spinner-border-sm`), o spinner inline do export, e o `fa fa-spinner fa-spin` + `[data-region="enrol-proccount"]` do pane de inscrição. Três formas para o mesmo conceito — é o que o card justifica unificar.

- [ ] **Step 2: Adicionar a linha de estado carregando**

Acrescentar ao final do corpo do preview, antes de qualquer callout:

```html
<div class="h">Carregando / ocupado — a lacuna que o hub tem hoje em 3 formas diferentes</div>
<div class="note" style="margin-bottom:8px;">
  Duas formas, só. Ambas usam o <code>spinner-border</code> do Bootstrap (traz o próprio
  <code>.75s linear infinite</code>).
</div>
<div class="row" style="align-items:flex-start;gap:16px;">
  <div style="flex:1;min-width:220px;">
    <div class="note" style="margin-bottom:4px;"><b>Pane</b> · troca de aba · <code>aria-busy="true"</code></div>
    <div style="border:var(--mds-stroke-sm) solid var(--mds-border-default);border-radius:var(--mds-radius-md);
                min-height:var(--mds-loading-min-height, 12rem);display:flex;align-items:center;justify-content:center;
                background:var(--mds-bg-surface-default);">
      <span style="width:2rem;height:2rem;border:3px solid var(--mds-bg-interactive-primary-default);
                   border-right-color:transparent;border-radius:50%;display:inline-block;"></span>
    </div>
  </div>
  <div style="flex:1;min-width:220px;">
    <div class="note" style="margin-bottom:4px;"><b>Botão</b> · disabled + spinner</div>
    <span class="btn" style="background:var(--mds-bg-interactive-primary-disabled);display:inline-flex;
                             align-items:center;gap:6px;">
      <span style="width:.75rem;height:.75rem;border:2px solid currentColor;border-right-color:transparent;
                   border-radius:50%;display:inline-block;"></span>
      Salvando…
    </span>
  </div>
</div>
<div class="note" style="margin-top:8px;">
  <b>Disciplina obrigatória</b> (o mtube erra isto): limpar o estado num <code>finally</code>, nunca só no
  caminho de sucesso — senão um load que falha deixa o pane girando pra sempre, sem retry.
</div>
```

- [ ] **Step 3: Verificar**

```bash
head -1 docs/design-kit/states.html
grep -nE '[T]ODO|FIXME|<<<<<<<' docs/design-kit/states.html || echo "OK"
```

Abrir no navegador, claro e escuro.

- [ ] **Step 4: Commit**

```bash
git add docs/design-kit/states.html
git commit -m "docs(kit): add the busy state the hub never had

states.html covered default/hover/active/disabled plus focus and stopped there,
which is why the plugin ships three unrelated one-off treatments for the same
idea: the import banner, the export button's inline spinner, and the enrol
pane's fa-spin.

Draws the two shapes the hub actually needs — a pane-level placeholder and a
disabled button with a spinner — and states the discipline mtube gets wrong:
clear the state in a finally, not on the success path."
```

---

### Task 3: `modal-shell.html` — to-be acoplado (D2)

**Files:**
- Modify: `docs/design-kit/modal-shell.html` (44 linhas hoje; vira dois painéis)

- [ ] **Step 1: Derivar o cabeçalho e o rodapé reais do código**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
# O restyle do .btn-close que É shipado (e que o card não desenha)
grep -n 'btn-close' styles.css | head
# Os headerlinks e a classe compartilhada
grep -rn 'headerlink' amd/src/central/ styles.css | head
grep -n 'local-dimensions-headerlink-modal' amd/src/central/participants_manager.js
# A bifurcação do rodapé: quem usa ModalSaveCancel vs core/modal puro
grep -rn 'ModalSaveCancel\|ModalDeleteCancel\|Modal.create\|setSaveButtonText' amd/src/central/ | head -20
```

Fatos esperados (confirme antes de desenhar): `.btn-close` → 1.75rem, radius 8px, `background-color:#e7f0f9`, pseudo-elemento FA `content:'\f00d'` em `#0f4d85` (escolhido porque o stylelint do Moodle proíbe SVG inline em data URI); `local-dimensions-headerlink-modal` adicionada em `participants_manager.js:153` e **também** usada pelo ModalForm de framework; `setSaveButtonText` existe em **um** call site (`competency_browser.js:93`).

- [ ] **Step 2: Reescrever o card com dois painéis (as-is | to-be)**

O card passa a ter dois painéis lado a lado, como as telas. Conteúdo obrigatório de cada um:

**as-is** — o que é shipado hoje:
- Cabeçalho: título + o **`.btn-close` restilizado real** (chip 1.75rem, radius 8px, fundo `#e7f0f9`, glifo `\f00d` em `#0f4d85`) — não o quadrado genérico `.m-x` de hoje.
- Slot de ação no cabeçalho com o headerlink (`btn btn-outline-secondary btn-sm` + `fa fa-arrow-up-right-from-square`), anotado "até 4, capability-gated, um visível por vez".
- Rodapé com a **bifurcação real**: `ModalSaveCancel`/`ModalDeleteCancel`/`ModalForm` têm rodapé; **7** superfícies `core/modal` puras shipam rodapé **vazio** com a ação primária no corpo.

**to-be** — D2:
- Cabeçalho: `[ícone] Título … [atualizar] [expandir] [fechar]`, botões 36×36 (30×30 <768px), com badge de origem `mtube: refresh` e `mtube: expand`.
- Expandir/estreitar como **dois botões sempre presentes**, CSS escolhe qual mostrar (badge `mtube: dois botões, zero JS de ícone`).
- Rodapé: links administrativos à esquerda (`btn btn-link p-0` + `fa fa-external-link`, `target="_blank" rel="noopener"`), ação primária à direita, `justify-content-between`. Badge `mtube: links no rodapé`.

- [ ] **Step 3: Escrever as três divergências deliberadas do mtube**

Adicionar um callout no card com exatamente estes três pontos (são o que impede a implementação de copiar errado):

```html
<div class="callout">
  <div class="t">Divergências deliberadas do mtube</div>
  <ul style="margin:4px 0 0;padding-left:16px;">
    <li><b>Restilizar o <code>.btn-close</code> do core, não substituir.</b> O mtube faz
        <code>.modal-header').empty()</code> e perde a wiring de dismiss e a a11y. A Central já faz o certo.</li>
    <li><b>Persistir o expandir na preferência de usuário</b> (<code>setUserPreference</code>), não em
        <code>localStorage</code> — que não segue o usuário entre navegadores e reintroduziria o que o
        commit <code>40fb4ad</code> removeu.</li>
    <li><b>Sem <code>!important</code></b>: todas as regras <code>.fullscreen</code> do mtube usam, e o
        <code>declaration-no-important</code> do CI reprova. A Central vence por especificidade
        (<code>.local-dimensions-*</code>), e usa pares <code>height</code>/<code>max-height</code> +
        <code>calc()</code> — nunca <code>min()</code> em altura (<code>csstree/validator</code>).</li>
  </ul>
</div>
```

- [ ] **Step 4: Escrever o que D2 aposenta**

Adicionar ao painel to-be a nota (o spec chama isto de dívida que a implementação esqueceria):

> Mover os links pro rodapé **retira a razão de existir** da classe compartilhada
> `local-dimensions-headerlink-modal` (2 consumidores: abas de participantes + form de framework) e da
> regra de CSS que encolhe o título para o link encostar no fechar. Aposentar as duas junto — senão
> sobra CSS órfão empurrando o título à toa.

- [ ] **Step 5: Atualizar o `@dsCard` da linha 1**

```html
<!-- @dsCard group="Shell" name="Modal shell" subtitle="Cabeçalho/corpo/rodapé — as-is vs to-be (atualizar, expandir, links no rodapé)" -->
```

- [ ] **Step 6: Verificar e commitar**

```bash
head -1 docs/design-kit/modal-shell.html
grep -nE '[T]ODO|FIXME|<<<<<<<' docs/design-kit/modal-shell.html || echo "OK"
git add docs/design-kit/modal-shell.html
git commit -m "docs(kit): model the real modal shell, and the mtube-inspired to-be

The card modelled a generic shell that matched none of the sixteen shipped
modals and hid the three things that actually vary: it drew a plain close
square rather than the restyled .btn-close that ships, had no header-action
slot even though participants_manager injects up to four gated links, and
implied every modal has Save/Cancel when seven plain core/modal surfaces ship
an empty footer with the action in the body.

The to-be couples the two changes that are really one: the header gains
refresh and expand, which is exactly why the admin links must move down to the
footer — the same pressure that moved mtube's. Records that this retires the
shared headerlink class and its title rule, and the three places we diverge
from mtube on purpose (restyle don't replace the close, user preference not
localStorage, no !important)."
```

---

### Task 4: `sticky-footer.html` — card novo

**Files:**
- Create: `docs/design-kit/sticky-footer.html`

- [ ] **Step 1: Derivar as três variantes reais**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
grep -oE 'data-action="[a-z-]+"' templates/central/structure_footer_actions.mustache | sort -u
grep -oE 'data-action="[a-z-]+"' templates/central/frameworks_footer_actions.mustache | sort -u
grep -n 'central-footer-actions\|sticky-footer' styles.css | head
grep -n 'sticky_footer\|set_auto_enable' central.php
```

Esperado — **confirme, não copie de memória**:
- estrutura: `edit`, `addchild`, `rules`, `links`, `related`, `moveto`, `delete`
- frameworks: `edit`, `duplicate`, `visibility`, `delete`
- planos (em `plans.mustache`): `edit-template`, `browse-frameworks`, `manage-participants`, `duplicate-template`, `delete-template`
- CSS: `#sticky-footer .local-dimensions-central-footer-actions { justify-content: safe center; overflow-x: auto; }` — o seletor de id vence deliberadamente o `overflow:hidden` do Boost
- `central.php`: um `\core\output\sticky_footer` com `set_auto_enable(false)`

- [ ] **Step 2: Criar o arquivo**

Linha 1 exata:

```html
<!-- @dsCard group="Shell" name="Sticky footer" subtitle="As 3 variantes reais — é o lançador de ~10 dos 17 modais" -->
```

Estrutura do preview: tokens inline (copiar o bloco `:root` + dark de `modal-shell.html` para manter o kit consistente), depois as três variantes empilhadas com rótulo, cada botão no padrão cru do core:

```html
<span class="sf-btn"><i class="ic"></i><span class="lab">Editar</span></span>
```

com `.sf-btn { display:flex; flex-direction:column; align-items:center; padding-top:0; padding-bottom:0; }` — ícone acima, rótulo centrado, **sem variante de cor** (é o padrão do core `btn py-0 d-flex flex-column align-items-center`).

Marcar em cada botão que abre modal um badge discreto `→ MOD.X`, para o card provar visualmente que é o lançador.

- [ ] **Step 3: Escrever o invariante no card — é a razão de ele existir**

```html
<div class="callout">
  <div class="t">Invariante — não é preferência estética</div>
  <ul style="margin:4px 0 0;padding-left:16px;">
    <li>Estes botões são o <b>lançador de ~10 dos 17 modais</b> do hub. Remover um deixa o modal
        <b>inalcançável</b> — não é mudança de layout.</li>
    <li><b>Nunca kebab por linha</b> para a entidade da aba (framework / nó / template), mesmo que um
        <code>.dc.html</code> do Claude Design mostre. O ⋮ do design é artefato; a intenção é footer.</li>
    <li><b>Fronteira da regra:</b> ela governa o CRUD da <i>entidade da aba</i>. Ações de competências
        <i>dentro da lista de um plano</i> são lista aninhada e <b>legitimamente usam kebab</b>
        (<code>plans.mustache:396-430</code>) — o sticky-footer daquela aba já está ocupado pelo template
        selecionado. Não "consertar" esse kebab.</li>
    <li>Um <code>\core\output\sticky_footer</code> por página (<code>central.php</code>,
        <code>set_auto_enable(false)</code>): as 3 abas dirigem <b>a mesma</b> superfície, com o HTML
        interno trocado e limpo na troca de aba.</li>
  </ul>
</div>
```

- [ ] **Step 4: Verificar e commitar**

```bash
head -1 docs/design-kit/sticky-footer.html
grep -nE '[T]ODO|FIXME|<<<<<<<' docs/design-kit/sticky-footer.html || echo "OK"
git add docs/design-kit/sticky-footer.html
git commit -m "docs(kit): card the sticky footer, the hub's most distinctive pattern

Nine components had a card and this one did not, even though it is the entry
point for roughly ten of the sixteen modals — links/related/rules/moveto on
Structure, browse-frameworks/manage-participants/duplicate-template/
delete-template on Plans, edit on all three. Removing a button there does not
restyle anything; it makes a modal unreachable.

Draws the three real variants and writes the invariant on the card itself,
including the boundary settled today: the no-kebab rule governs the tab's
primary entity, so the per-competency kebab inside a plan's list is correct and
must not be 'fixed'."
```

---

### Task 5: `moodle-ds-alignment.md` — restrições de plataforma

**Files:**
- Modify: `docs/design-kit/moodle-ds-alignment.md`

- [ ] **Step 1: Ler o documento e achar onde a seção entra**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
grep -n '^#' docs/design-kit/moodle-ds-alignment.md
```

- [ ] **Step 2: Adicionar a seção "Restrições de plataforma"**

Conteúdo obrigatório (todos verificados em `f84d30a` contra v4.5.12 e o checkout 5.1 — não re-litigar):

```markdown
## Restrições de plataforma

O que decide o que pode ser construído. Registrado uma vez aqui, em vez de re-litigado por superfície.

### Boundary do stylelint do CI

O CI roda a config **do próprio Moodle**, mais estrita que o `.stylelintrc.json` local e não
reproduzível pelo `npx stylelint`. Duas regras têm que ser pré-empetidas na hora de escrever:

- `declaration-no-important` — **nunca** `!important`. Se uma utility do Bootstrap no markup brigar com
  a propriedade, tire a utility do template e assuma a propriedade numa classe do plugin.
- `csstree/validator` — rejeita valores que a gramática (mais velha) não conhece: `clamp()`/`min()`/`max()`
  em propriedades tipo `height` falham com "Invalid value". Use pares `height` + `min-height`/`max-height`;
  `calc()` é aceito.

### Bootstrap 4 (Moodle 4.5) vs Bootstrap 5 (5.x)

Componentes wired por markup precisam dos **dois** data-attributes lado a lado
(`data-toggle` + `data-bs-toggle`) e das duas classes de alinhamento
(`dropdown-menu-right` + `dropdown-menu-end`). Seletores JS precisam casar os dois.

Três fatos **verificados** em `f84d30a` (v4.5.12 + checkout 5.1) — o tipo de coisa que só se
descobre uma vez:

| Fato | Consequência |
|---|---|
| O 4.5 **não define nenhuma custom property `--bs-*` de modal** (`--bs-modal-width`, `--bs-modal-margin`…) | Nunca dimensionar modal por var BS5. Use as classes do próprio Bootstrap (`modal-xl` é idêntico no 4 e no 5) ou dê fallback: `var(--bs-modal-margin, 1.75rem)` |
| O BS5 (`EventHandler.trigger`) dispara **evento jQuery E nativo**; o BS4 dispara **só jQuery** | Um listener **jQuery** cobre os dois branches. `addEventListener` cobre só o 5.x — é a assimetria que matou o `context.js` |
| `lib/amd/src/first.js` seta `window.jQuery`, então o BS5 **sempre** toma o caminho jQuery | O bind jQuery não é gambiarra: é o caminho que o core garante nos dois |

Custo de ignorar isto: dois defeitos silenciosos no 4.5, corrigidos em `f84d30a` — o contador da
contextbar não seguia a aba e o modal de participantes caía para 500px com quatro abas dentro.
```

- [ ] **Step 3: Commit**

```bash
git add docs/design-kit/moodle-ds-alignment.md
git commit -m "docs(kit): record the platform constraints that decide what we can build

The kit's strongest document said nothing about the two things that actually
gate a design here: the CI stylelint boundary (no !important; no clamp/min/max
in height-like properties) and the BS4-vs-BS5 split that just produced two
silent 4.5 defects.

Records the three platform facts verified in f84d30a against v4.5.12 — 4.5
defines no --bs-* modal custom properties, BS5 fires both jQuery and native
events while BS4 fires only jQuery, and core sets window.jQuery so BS5 always
takes the jQuery path — so the next surface does not rediscover them."
```

---

# FASE 2 — Varredura por superfície

> ## CORREÇÃO GLOBAL do IMP-03 — leia antes de qualquer tarefa desta fase
>
> **"Loading na troca de aba" é formulação ERRADA em todo lugar deste plano.** Verificado na
> Task 7 e reconferido por mim: a troca de aba de verdade **já tem** loading, e vem do **core** —
> `dynamic_tabs.js:92-97` liga `shown.bs.tab` → `loadTab` → `addIconToContainer` (`:153`), e
> `:88` faz `previousTab.textContent = ''`. Quem clica numa aba **vê** carregamento hoje.
>
> **A lacuna real é o `reloadPane` do plugin** (`tabs.js:51-66`): é *o `loadTab` do core menos o
> ícone*, e roda nos **23 call sites** (structure 9, frameworks 6, plans 6, browser 1, context 1)
> — todos refreshes automáticos pós-ação. É aí que o conteúdo velho fica parado sem sinal nenhum.
>
> Onde estiver escrito "loading na troca de aba", leia **"loading no `reloadPane`"**. E
> **reavalie a Task 10** antes de executá-la: o mount preguiçoso do modal de participantes pode
> repousar na mesma premissa errada.


> **Método obrigatório em toda tarefa desta fase**, nesta ordem: (1) derivar do código, (2) atualizar o mapa, (3) atualizar a tela, (4) verificar que toda ref `arquivo:linha` resolve. Cada tarefa = 1 commit.

### Task 6: `BAR` — contextbar (mapa) + contador (D5)

**Files:**
- Modify: `docs/design-kit/maps/bar-contextbar.md`

- [ ] **Step 1: Derivar**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
grep -n 'context-count\|count-value\|data-mode\|systemframeworkcount\|systemtemplatecount' templates/central/contextbar.mustache
grep -n 'renderCounter\|activeMode\|tabContent' amd/src/central/context.js
```

- [ ] **Step 2: Atualizar o mapa com o estado pós-`f84d30a`**

O `context.js` mudou em `f84d30a`: `tabToggle` agora é
`'.dynamictabs a[data-toggle="tab"], .dynamictabs a[data-bs-toggle="tab"]'` (:57) e o bind é
jQuery (:282). **Toda ref de linha do mapa para `context.js` tem que ser refeita** — o arquivo
mudou 30 linhas.

- [ ] **Step 3: Registrar D5 — o contador e a alternativa**

Adicionar ao mapa, no elemento do contador:

> **Decisão (D5, 2026-07-14):** a contextbar conta o **contexto** (Sistema/Categoria), não a aba —
> logo o número está certo e o **substantivo** é que erra na aba Competências, onde `activeMode()`
> cai para `'structure'` e o rótulo diz "Estruturas" enquanto o subheader da aba mostra a contagem
> de competências.
> **Alternativa registrada e descartada:** fazer o contador seguir a aba ativa. Descartada porque
> contraria o propósito declarado da contextbar. Não re-litigar sem mudar esta nota.
> **Contexto:** o hub tem **três** contadores (contextbar, toolbar de frameworks, subheader da
> estrutura) onde o mtube tem um.

- [ ] **Step 4: Registrar o refresh (IMP-05) como to-be**

> **to-be (IMP-05, `mtube: refresh`):** a contextbar ganha um controle de atualizar, reusando o
> `reloadPane` que já existe (`tabs.js:37-66`) e que hoje nada expõe. **Não** vai no sticky-footer:
> ele é escopado por seleção e é limpo na troca de aba. Sem string nova — o pane de inscrição já
> shipa `{{#str}}refresh, moodle{{/str}}` + `fa fa-rotate`. Copiar a disciplina do mtube
> (disabled + `fa-spin` num `finally`); **não** copiar o defeito dele de deixar o subtítulo stale.

- [ ] **Step 5: Verificar as refs**

```bash
# Toda ref arquivo:linha do mapa tem que resolver. Para cada uma, conferir:
sed -n '57p;282p' amd/src/central/context.js
```

- [ ] **Step 6: Commit**

```bash
git add docs/design-kit/maps/bar-contextbar.md
git commit -m "docs(kit): resync the contextbar map and settle the counter reading"
```

---

### Task 7: `EST` — estrutura (tela + mapa)

**Files:**
- Modify: `docs/design-kit/screens/est-structure.html` (387 linhas — a maior do kit)
- Modify: `docs/design-kit/maps/est-structure.md`

- [ ] **Step 1: Derivar**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
grep -oE 'data-action="[a-z-]+"' templates/central/structure_footer_actions.mustache | sort -u
grep -n 'data-action="moveto"\|data-action="related"' templates/central/structure_footer_actions.mustache
grep -n 'toolbar\|search\|resizer\|display-options' templates/central/structure.mustache | head
```

- [ ] **Step 2: Corrigir o as-is — quatro divergências conhecidas**

O painel congelou em 2026-06-29. Divergências a corrigir:
1. As ações de detalhe **saíram do pane** para o sticky-footer (`structure_footer_actions.mustache:41-65`).
2. `moveup`/`movedown` foram **substituídos** por um único `moveto` (:61) que abre o `move_competency_modal`.
3. `related` (:57) é **novo**.
4. Toda a camada de toolbar/busca/resizer/display-options está **ausente**.

- [ ] **Step 2b: O mapa está com 100% das refs quebradas — medido na Task 6, não estimado**

Varredura feita durante a Task 6: **todas as 23** refs de `structure.mustache` no
`maps/est-structure.md` resolvem para linhas **não relacionadas**. Exemplo concreto:
`EST-FW-COUNT` afirma `:95`, que é um `<script>` de JSON; o real está em `:121-122`.
**Não faça spot-fix — refaça todas.** E note o padrão da Task 6: o defeito de um mapa não é só a
ref velha; é também a **ausência** — lá, o comportamento inteiro do `context.js` não estava
mapeado, e o mapa tinha zero refs de JS. Confira o que falta, não só o que envelheceu.

- [ ] **Step 3: Aposentar os IDs mortos no mapa**

`EST-DETAIL-MOVEUP` e `EST-DETAIL-MOVEDOWN` **não existem mais**. Marcar como **aposentados** no
mapa, apontando para `MOD.MOVETO` (Task 16) — não deixar pendurados, e não reaproveitar os IDs.

- [ ] **Step 4: Adicionar ao to-be as melhorias que caem aqui**

- IMP-03 loading na troca de aba (badge `mtube: loading`), com a ressalva escrita: **não** aplicar em
  `refreshNode` — é caminho in-place deliberado, e um spinner de pane inteiro ali seria regressão.
- IMP-05 refresh na contextbar (badge `mtube: refresh`).
- IMP-10 ícones + indicador `inset 0 -2px 0` nas abas (badge `mtube: tab icons`), escopado sob
  `.local-dimensions-central-page` para não vazar para outros consumidores de `dynamic_tabs`.

- [ ] **Step 5: Verificar e commitar**

```bash
head -1 docs/design-kit/screens/est-structure.html
git add docs/design-kit/screens/est-structure.html docs/design-kit/maps/est-structure.md
git commit -m "docs(kit): resync the Structure screen and retire the dead move IDs"
```

---

### Task 8: `FWK` — frameworks (tela + mapa)

**Files:**
- Modify: `docs/design-kit/screens/fwk-frameworks.html`
- Modify: `docs/design-kit/maps/fwk-frameworks.md`

- [ ] **Step 1: Derivar**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
sed -n '78,90p' templates/central/frameworks.mustache
grep -n 'canscalespage\|hasexcluded\|excludedcount' templates/central/frameworks.mustache
grep -rn 'alert alert-info\|spinner-border' templates/central/ classes/form/ | head
```

- [ ] **Step 2: Corrigir o as-is**

1. Linha 44 mostra **uma** ação de cabeçalho (`＋ Novo framework`); a aba ship **três**:
   `data-action="new"` (:80), `data-action="import"` (:83), `data-action="export"` (:87, gated
   `{{#canexport}}`).
2. Linha 52 desenha as 4 ações de linha como badges **dentro da linha**; hoje são botões do
   sticky-footer.
3. Não mapeados: `data-canscalespage` (:63 — atalho do cabeçalho de escalas, de `a2112fe`) e o
   contador de frameworks ocultos (`hasexcluded`/`excludedcount`, str `central_frameworks_hiddencount`).

- [ ] **Step 3: Desenhar o banner do import como referência do IMP-03**

O modal de import é o **único** loading de nível de corpo do plugin (`alert alert-info` +
`spinner-border spinner-border-sm`). Desenhar aqui e anotar: é a forma de referência para o
spinner do IMP-03, e a prova de que o hub já sabe fazer — só não faz nas abas.

- [ ] **Step 4: Verificar e commitar**

```bash
git add docs/design-kit/screens/fwk-frameworks.html docs/design-kit/maps/fwk-frameworks.md
git commit -m "docs(kit): resync the Frameworks screen — three header actions, footer rows"
```

---

### Task 9: `PLN` — planos (tela + mapa) + a fronteira do kebab

**Files:**
- Modify: `docs/design-kit/screens/pln-plans.html`
- Modify: `docs/design-kit/maps/pln-plans.md`

- [ ] **Step 1: Derivar**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
sed -n '396,445p' templates/central/plans.mustache
sed -n '465,481p' templates/central/plans.mustache
grep -n 'remove-filter-competency\|add-filter-competency\|display-options\|drag-handle' templates/central/plans.mustache
```

- [ ] **Step 2: Corrigir o as-is — está dois reworks atrás**

1. Ações de competência por linha **não são mais botões de ícone**: são `dropdown-item` num kebab —
   `edit-competency` (:405), `move-competency-up` (:411), `move-competency-down` (:417),
   `move-competency-to` (:423), `remove-competency` (:430) — mais
   `open-competency-detail` (:382) e um `[data-region="drag-handle"]` (:441).
2. Ações da aba foram pro sticky-footer (:465-481) e ganharam `duplicate-template` (:477).
3. Não desenhados: o filtro de chips multi-competência (`remove-filter-competency` :202,
   `add-filter-competency` :208) e as duas engrenagens de display-options (:147, :300).

- [ ] **Step 3: Escrever a fronteira do kebab no card — decisão de 2026-07-14**

Este é o ponto que o próximo leitor iria adivinhar errado. Escrever no card **e** no mapa:

> **A fronteira da regra do sticky-footer (decidida 2026-07-14).** A regra "nunca kebab por linha"
> governa o **CRUD da entidade da aba** (framework / nó da estrutura / template) — que vai pro
> sticky-footer porque é o que lança os modais. As ações de **competências dentro da lista de um
> plano** são **lista aninhada** e legitimamente usam kebab (`plans.mustache:396-430`): o
> sticky-footer desta aba já está ocupado pelas ações do template selecionado. **Este kebab está
> correto — não "consertar".**

- [ ] **Step 4: Verificar e commitar**

```bash
git add docs/design-kit/screens/pln-plans.html docs/design-kit/maps/pln-plans.md
git commit -m "docs(kit): resync the Plans screen and draw the kebab boundary

The as-is panel was two reworks behind: per-competency actions are dropdown-items
in a kebab now, the tab actions moved to the sticky footer and gained
duplicate-template, and the chip filter and two display-options gears were never
drawn.

Writes down the boundary settled today, which the next reader would otherwise
guess wrong: the no-kebab rule governs the tab's primary entity, so this
per-competency kebab is correct — that tab's sticky footer is already spoken for
by the selected template."
```

---

### Task 10: `MOD.PART` — participantes (tela + mapa)

**Files:**
- Modify: `docs/design-kit/screens/mod-participants.html`
- Modify: `docs/design-kit/maps/mod-participants.md`

- [ ] **Step 1: Derivar**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
sed -n '60,72p;148,154p' templates/central/participants_manager.mustache
sed -n '145,160p' amd/src/central/participants_manager.js
grep -n 'injectHeaderLinks\|headerlink' amd/src/central/participants_manager.js
```

- [ ] **Step 2: Corrigir o as-is — a 4ª aba e o pós-`f84d30a`**

1. A linha 51 rotula "modal com 3 abas" e a 55 desenha três; **shipou uma quarta** em `3d1d5cb`
   (`participants_manager.mustache:66-68`, pane em `:150-151`) — 70 minutos depois do último commit
   do kit. O subtítulo `@dsCard` da linha 1 ainda diz "(Coortes/Usuários/Papéis)" — **corrigir para
   incluir Métodos de inscrição**.
2. **Pós-`f84d30a`**: o diálogo agora recebe `modal-xl` + `local-dimensions-participants-modal` +
   `local-dimensions-headerlink-modal` (`participants_manager.js:153`). O `--bs-modal-width` **não
   existe mais** — não desenhar.
3. Desenhar os até-4 header links capability-gated (`<a target="_blank" class="btn
   btn-outline-secondary btn-sm local-dimensions-headerlink d-none">` + `fa
   fa-arrow-up-right-from-square me-1`, `header.insertBefore(link, closebtn)`, um visível por vez) —
   é a coisa mais próxima que o hub tem da fileira de ações do mtube.

- [ ] **Step 3: to-be — IMP-08 e IMP-03**

- Expandir/estreitar (badge `mtube: expand`): esta é a superfície mais densa do hub (4 abas, tabela
  de coortes, form de papéis, árvore de inscrição). Ponto de ancoragem: a mesma fileira dos header
  links.
- Loading no mount preguiçoso (badge `mtube: loading`): `ensureMounted`
  (`participants_manager.js:171-185`) monta o pane na primeira ativação **sem placeholder** — o pane
  fica vazio até o WS resolver.
- Os header links **descem pro rodapé** (D2) — e isso aposenta `local-dimensions-headerlink-modal`
  (ver Task 3).

- [ ] **Step 4: Verificar e commitar**

```bash
head -1 docs/design-kit/screens/mod-participants.html   # subtítulo com 4 abas
git add docs/design-kit/screens/mod-participants.html docs/design-kit/maps/mod-participants.md
git commit -m "docs(kit): resync the participants modal — fourth tab, modal-xl, header links"
```

---

### Task 11: `MOD.ENROL` — métodos de inscrição (tela + mapa)

**Files:**
- Modify: `docs/design-kit/screens/mod-enrolmethods.html`
- Modify: `docs/design-kit/maps/mod-enrolmethods.md`

- [ ] **Step 1: Derivar — redesenhar do template shipado, não do que o kit diz**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
grep -n 'data-region=\|data-action=' templates/central/enrol_methods.mustache | head -30
ls templates/central/enrol_row.mustache 2>/dev/null || echo "enrol_row.mustache foi DELETADO (33f7697)"
```

- [ ] **Step 2: Corrigir — foi desenhado como proposta e superado no mesmo dia**

Desenhado como to-be em 2026-07-11 e superado pelo código shipado na mesma noite + 14 follow-ups.
Concretamente errado ou ausente:
1. O controle segmentado de método é `cohort`|`self` — **nunca `sync`**.
2. Ausentes: a busca de competência (`data-region="enrol-search"`, `ec9d813`); o gate de ambos-métodos-
   desabilitados (`[data-region="enrol-disabled"]`, `1d15e9f`); os **três** botões
   `data-action="enrol-refresh"` (um por região); o toggle enable/disable por linha (`a5ef9a8`,
   backed by `set_enrol_instance_status`); o contador de processamento.
3. `545ba17` transformou o acordeão em **tabela rotulada**; `33f7697` **deletou** o
   `enrol_row.mustache` em favor de linhas construídas via DOM.
4. **Dropar o enquadramento de to-be** — isto é as-is agora.

- [ ] **Step 3: Marcar os 3 refresh como o precedente visual do IMP-05**

Os três `enrol-refresh` são a **única** afordância de refresh do hub e o precedente visual do IMP-05
(`btn btn-outline-secondary btn-sm` + `fa fa-rotate me-1` + `{{#str}}refresh, moodle{{/str}}`).
Anotar isso no mapa — é de onde o IMP-05 tira ícone e string sem inventar nada.

- [ ] **Step 4: Verificar e commitar**

```bash
git add docs/design-kit/screens/mod-enrolmethods.html docs/design-kit/maps/mod-enrolmethods.md
git commit -m "docs(kit): redraw the enrolment methods modal from the shipped template"
```

---

### Task 12: `MOD.RELATED` — competências referenciadas (tela + mapa) · caso de referência do IMP-06

**Files:**
- Modify: `docs/design-kit/screens/mod-related.html`
- Modify: `docs/design-kit/maps/mod-related.md`

- [ ] **Step 1: Derivar**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
grep -n 'add-selected\|btn-primary' templates/central/related_competencies.mustache
grep -n 'competency_tree_browser\|addToastRegion' amd/src/central/related_competencies.js
ls amd/src/central/related_datasource.js 2>/dev/null || echo "related_datasource.js DELETADO (44ac031)"
```

- [ ] **Step 2: Corrigir o as-is**

Antecede `44ac031`, que reconstruiu o caminho de adicionar sobre o `competency_tree_browser`
compartilhado e **deletou** o `related_datasource.js`. O as-is deve mostrar o que está lá e é
discutível: um rodapé do core **vazio**, com a ação primária
`<button class="btn btn-primary btn-sm" data-action="add-selected" disabled>` **no corpo**.

- [ ] **Step 3: Desenhar o to-be do IMP-06 — os dois lados**

Esta é a tela de referência do IMP-06. Desenhar **ambos**:
- **as-is**: botão no corpo, rodapé vazio.
- **to-be**: `ModalSaveCancel` + `setSaveButtonText('Add selected')` — exatamente a chamada que o
  `competency_browser.js:93` já faz um arquivo adiante. Badge `mtube: ação no rodapé`.
  Anotar: isto é **menos** código, não mais.

- [ ] **Step 4: Verificar e commitar**

```bash
git add docs/design-kit/screens/mod-related.html docs/design-kit/maps/mod-related.md
git commit -m "docs(kit): resync the related-competencies modal, the IMP-06 reference case"
```

---

### Task 13: `MOD.LINKS` — cursos e atividades (tela + mapa)

**Files:**
- Modify: `docs/design-kit/screens/mod-links.html`
- Modify: `docs/design-kit/maps/mod-links.md`

- [ ] **Step 1: Derivar — o shell sobrevive, as linhas não**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
git log --oneline -8 -- amd/src/central/competency_links.js
grep -n 'addToastRegion\|course-card\|completion' amd/src/central/competency_links.js | head
```

- [ ] **Step 2: Corrigir o as-is**

O `competency_links.mustache` está intocado desde 2026-06-28, então o **shell sobrevive** — mas as
linhas são construídas por JS, e o `competency_links.js` foi reformulado **seis vezes** depois de a
tela ser desenhada: `93e4f69` (cards de curso, atividades com checkbox, badges de conclusão),
`d7578b3` (busca de atividade, linhas de duas linhas, correções de contagem e contraste),
`7902bd8` (tratamento de erro resiliente a rede), `c10acd0` (strings em lote + chevron unificado do
autocomplete), `e0fe81d` (toast ao remover vínculo de curso). **O painel as-is desenha linhas que não
existem mais.**

- [ ] **Step 3: to-be + a referência do toast**

- IMP-08 expandir/estreitar (badge `mtube: expand`) — segunda superfície candidata.
- Esta é a tela de **referência da região de toast hospedada no modal**: `addToastRegion(modal.getBody()[0])`
  no `ModalEvents.shown`, porque a `.toast-wrapper` da página é `z-index:1051` e o modal é `1055`.
  Desenhar e anotar — o README vai apontar pra cá (Task 18).

- [ ] **Step 4: Verificar e commitar**

```bash
git add docs/design-kit/screens/mod-links.html docs/design-kit/maps/mod-links.md
git commit -m "docs(kit): redraw the course/activity link rows after six JS reworks"
```

---

### Task 14: `MOD.BROWSER` — procurar em frameworks (tela + mapa)

**Files:**
- Modify: `docs/design-kit/screens/mod-browser.html`
- Modify: `docs/design-kit/maps/mod-browser.md`

- [ ] **Step 1: Derivar**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
sed -n '85,100p' amd/src/central/competency_browser.js
grep -n 'form-select\|alert alert-info' templates/central/competency_browser.mustache
```

- [ ] **Step 2: Corrigir o as-is**

`competency_browser.mustache` mudou em `44ac031` (depois da tela), quando a árvore foi extraída para
uso compartilhado. A tela deve refletir o shell shipado: `ModalSaveCancel` com
`setSaveButtonText('Add selected')` — a **única** chamada de `setSaveButtonText` do plugin, e o padrão
que o IMP-06 generaliza —, um `<select class="form-select">` de framework acima da árvore
compartilhada, um corpo `alert alert-info` quando não há frameworks, e a regra "seleções são por
framework" (resetam ao trocar).

- [ ] **Step 3: Verificar e commitar**

```bash
git add docs/design-kit/screens/mod-browser.html docs/design-kit/maps/mod-browser.md
git commit -m "docs(kit): resync the competency browser onto the shared tree"
```

---

### Task 15: `MOD.DELPLANS` — excluir template com planos (tela + mapa)

**Files:**
- Modify: `docs/design-kit/screens/mod-delplans.html`
- Modify: `docs/design-kit/maps/mod-delplans.md`

- [ ] **Step 1: Derivar — e corrigir a localização do arquivo**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
ls templates/delete_template_modal.mustache          # raiz de templates/, NÃO em central/
sed -n '247,259p' amd/src/central/plans.js
grep -rn 'template_has_related_data\|deleteCancelPromise' amd/src/central/plans.js | head
```

- [ ] **Step 2: Promover o to-be a as-is**

O painel as-is (linhas 44-46) desenha o diálogo **pré**-to-be (um genérico "Este template tem planos
de aprendizagem. O que fazer com eles?" + dois radios pelados) enquanto a linha 60 ainda descreve o
to-be como **não construído**. Ele foi construído em `820a449`: `plans.js:247-259` renderiza
`local_dimensions/delete_template_modal` com `{name, plancount}` dentro de um `ModalDeleteCancel`,
`unlink` marcado por padrão, com uma nota de consequência por opção. **Promover o to-be a as-is.**

- [ ] **Step 3: Corrigir dois fatos que a tela erra**

1. **Localização**: o template é `templates/delete_template_modal.mustache`, na **raiz** de
   `templates/`, **não** em `templates/central/`.
2. **Fluxo alternativo nunca mencionado**: o modal só aparece quando
   `core_competency_template_has_related_data` é verdadeiro; senão o fluxo cai num
   `Notification.deleteCancelPromise` do core. Desenhar ou ao menos anotar os dois caminhos.

- [ ] **Step 4: Verificar e commitar**

```bash
git add docs/design-kit/screens/mod-delplans.html docs/design-kit/maps/mod-delplans.md
git commit -m "docs(kit): promote the delete-template to-be to as-is; fix its path and fallback"
```

---

### Task 16: `MOD.RULE` + `MOD.SCALE` — spot-check (4 arquivos limpos)

**Files:**
- Verify: `docs/design-kit/screens/mod-rule.html`, `docs/design-kit/maps/mod-rule.md`
- Verify: `docs/design-kit/screens/mod-scale.html`, `docs/design-kit/maps/mod-scale.md`

- [ ] **Step 1: Confirmar que continuam limpos**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
git log --oneline -3 -- templates/central/rule_config.mustache          # esperado: a78c3f6 (2026-06-27)
git log --oneline -3 -- templates/central/framework_scaleconfig.mustache # esperado: 283e9a7 (2026-06-28)
```

Ambos anteriores aos mapas — são os **únicos** 4 arquivos do kit cujas refs de linha ainda resolvem.

- [ ] **Step 2: Spot-check das duas regras que a tela do rule deveria mostrar**

```bash
grep -n 'preventDefault\|data-region="error"' amd/src/central/rule_config.js | head
grep -rn "getString('competencyrule'" amd/src/central/ | head
```

Confirmar: o caminho de pontos inválidos chama `event.preventDefault()` e **des-esconde** o alerta
inline `[data-region="error"]` em vez de fechar; e o título é a string **do core**
`getString('competencyrule', 'tool_lp')`.

- [ ] **Step 3: Registrar a lacuna no lugar certo — o README, não estas telas**

As três mudanças de escala de julho (`a2112fe` atalho do cabeçalho de escalas + paridade de escala
nativa, `8ab5635` travar o select de escala congelada, `c8901c0` select congelado não dispara mais a
regra de required) caíram todas em `classes/form/framework_dynamic_form.php`, **não** neste modal —
por isso a tela está limpa. A consequência **é** do README: o **lado formulário** do `MOD.SCALE` está
descoberto, porque o kit não mapeia **nenhum** corpo de `dynamic_form`. Isso entra na Task 18.

- [ ] **Step 4: Commit (só se o spot-check achar divergência)**

Se os quatro arquivos passarem, **não há commit** nesta tarefa — registre no relatório da tarefa que
foram verificados e estão corretos. Não commite "no-op".

---

### Task 17: Componentes as-is (6 arquivos)

**Files:**
- Modify: `docs/design-kit/form-section.html`, `image-dropzone.html`, `hierarchy-nav.html`,
  `master-detail.html`, `paginated-picker.html`, `cohort-assign.html`

- [ ] **Step 1: Conferir cada componente contra o código que ele afirma espelhar**

Estes seis expressam a visão to-be e **divergem de propósito** da UI shipada (o README já diz isso).
A tarefa **não** é forçá-los a virar as-is — é corrigir o que virou **falso**, não o que é
proposta. Para cada um, achar o código correspondente e conferir:

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
# hierarchy-nav: as 3 abas + contextbar — IMP-10 (ícones + indicador) cai aqui
grep -n 'tablabels\|dynamic_tabs' central.php
# paginated-picker: busca server-side + paginação
grep -rn 'ruleoutcome' templates/central/ amd/src/central/ | head -3
# cohort-assign: atribuição a coortes + sync
grep -n 'data-action=' templates/central/cohort_manager.mustache | head
```

- [ ] **Step 2: `hierarchy-nav.html` — adicionar o to-be do IMP-10**

Ícones nas abas + indicador ativo `inset 0 -2px 0 var(--bs-primary)` (badge `mtube: tab icons`).
Escrever as duas ressalvas:
- O `core/dynamic_tabs` já faz triple-stash de `displayname`, então **não precisa mudar template** —
  o ícone entra pelo rótulo.
- Escopar sob `.local-dimensions-central-page` para **não vazar** para outros consumidores de
  `dynamic_tabs` no site.
- **Não** portar o dropdown de overflow por ResizeObserver do mtube: ~130 linhas de medição para três
  rótulos curtos que nunca transbordam.

- [ ] **Step 3: Registrar a ilha Material como divergência conhecida**

Anotar (em `hierarchy-nav.html` ou no `tokens.html`, onde couber): o filtro de chips **do aluno** tem
14 tokens numa paleta **Google Material** (`#1a73e8` Blue 600, `#5f6368` Grey 700, `#f1f3f4` Grey 100)
— **não** o azul Boost `#0f6cbf` do kit. É a maior inconsistência interna do plugin: a navegação
primária do **admin** não ganhou nada enquanto o filtro do **aluno** ganhou um platter com indicador
de mola. Registrar como divergência conhecida, não "consertar" agora.

- [ ] **Step 4: Commit**

```bash
git add docs/design-kit/form-section.html docs/design-kit/image-dropzone.html \
        docs/design-kit/hierarchy-nav.html docs/design-kit/master-detail.html \
        docs/design-kit/paginated-picker.html docs/design-kit/cohort-assign.html
git commit -m "docs(kit): resync the component cards; give the hub tabs icons in the to-be"
```

---

# FASE 3 — Criações

### Task 18: `maps/mod-usage.md` — novo

**Files:**
- Create: `docs/design-kit/maps/mod-usage.md`

- [ ] **Step 1: Derivar**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
cat templates/central/competency_usage_modal.mustache
sed -n '1190,1232p' amd/src/central/structure.js
grep -rn 'local_dimensions_competency_usage' classes/external/ db/services.php | head
```

- [ ] **Step 2: Escrever o mapa**

`competency_usage_modal.mustache` (`ec028d5`, 2026-07-02) não tem mapa — `grep -rln
'competency_usage_modal' docs/design-kit/` não retorna nada, contradizendo o README:48. IDs para as
três seções (`managecompetencies_linkedcourses`, `managecompetencies_linkedactivities`,
`central_structure_linkedplans`), origem `structure.js:1190-1232`, e **as duas regras invisíveis no
template**:
1. Só a seção **clicada** renderiza, embora a WS `local_dimensions_competency_usage` retorne **as
   três**.
2. As linhas são **deliberadamente não-navegáveis** — `<li class="py-1 border-bottom">` num
   `list-unstyled`.

Seguir o formato dos mapas existentes (ID, rótulo, tipo, origem, dados, regra de negócio) e a
convenção `MOD.USAGE-…`.

- [ ] **Step 3: Commit**

```bash
git add docs/design-kit/maps/mod-usage.md
git commit -m "docs(kit): map the competency usage modal"
```

---

### Task 19: `maps/mod-moveto.md` — novo

**Files:**
- Create: `docs/design-kit/maps/mod-moveto.md`
- Modify: `docs/design-kit/maps/est-structure.md` (fechar os IDs aposentados da Task 7)

- [ ] **Step 1: Derivar os dois call sites**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
sed -n '558,573p' amd/src/central/plans.js
sed -n '972,1007p' amd/src/central/structure.js
cat templates/central/move_competency_modal.mustache
```

- [ ] **Step 2: Escrever o mapa — dois consumidores, um template**

- `plans.js:558-573`: lê linhas `[data-competency]`, salva via
  `core_competency_reorder_template_competency`, depois **reposiciona in place** porque "o core põe a
  linha **depois** do ocupante ao mover para baixo, e **antes** ao mover para cima".
- `structure.js:972-1007`: construído a partir de `nodeSiblings`, **desiste** quando
  `siblings.length < 2`, e o `persistNodeMove` **agrupa** os movimentos de um passo — "o caminho
  prático para ramos longos, e o do teclado".
- Registrar `EST-DETAIL-MOVEUP`/`EST-DETAIL-MOVEDOWN` como **aposentados** aqui, cruzando com a
  Task 7. Não reaproveitar os IDs.

- [ ] **Step 3: Commit**

```bash
git add docs/design-kit/maps/mod-moveto.md docs/design-kit/maps/est-structure.md
git commit -m "docs(kit): map the move-to modal and close the retired move IDs"
```

---

### Task 20: `MOD.STRUCTRELATED` — mapa + tela (novos)

**Files:**
- Create: `docs/design-kit/maps/mod-structrelated.md`
- Create: `docs/design-kit/screens/mod-structrelated.html`

- [ ] **Step 1: Derivar o contrato de CSS — é o único lugar onde ele seria escrito**

```bash
cd /Volumes/N1TB/dev/github/moodle/public/local/dimensions
cat templates/central/structure_related_modal.mustache
cat templates/central/structure_related_chips.mustache
grep -n 'local-dimensions-related-modal' styles.css
grep -n 'data-action="related"' templates/central/structure_footer_actions.mustache
grep -rn 'structure_detail_content\|linksclickable\|showrelated' amd/src/central/ | head
```

- [ ] **Step 2: Escrever o mapa com o contrato completo**

Tudo shipou em `47677dd` (2026-07-09) sem mapa e sem IDs. O mapa carrega o **contrato de CSS**,
porque é o único lugar onde ele existiria:
- cabeçalho **escondido** (`display: none`) — o título passado **nunca renderiza**;
- diálogo capado em **620px** com radius **24px** (escolhido para casar com o card, porque o
  `core/modal` foca o `.modal-dialog`);
- conteúdo **transparente**, corpo com padding **zero**;
- botão de fechar **do corpo**, colorido em JS pelo `textcolor` da competência;
- **exclusão explícita** do restyle global do `.btn-close`
  (`.modal:not(.local-dimensions-related-modal):has(…)`).

Registrar também: o **segundo ponto de entrada** — os nomes clicáveis de competência da aba Planos
chegam no mesmo modal via `competency_detail.js` — e o reuso `{linksclickable: false, showrelated:
false}` do `structure_detail_content`, que **impede empilhar** um modal de uso por cima deste.

- [ ] **Step 3: Criar a tela — painel as-is único**

Linha 1:

```html
<!-- @dsCard group="Telas (as-is ↔ to-be)" name="Modal · Competências relacionadas (estrutura)" subtitle="MOD.STRUCTRELATED — diálogo sem cabeçalho: o card É o diálogo" -->
```

**Um único painel as-is** — nada é proposto mudar aqui, então não desenhar to-be. IDs no botão de
fechar, na faixa de cabeçalho, no texto dirigido por cor e nas contagens não-interativas.

Motivo de a tela existir (escrever no card): é a superfície mais incomum do plugin — um diálogo sem
cabeçalho onde **o card é o diálogo** — e o único lugar em que um leitor olhando a UI shipada não
consegue inferir a construção sem ler três arquivos.

- [ ] **Step 4: Commit**

```bash
git add docs/design-kit/maps/mod-structrelated.md docs/design-kit/screens/mod-structrelated.html
git commit -m "docs(kit): cover the headless related-competencies dialog"
```

> **Deliberadamente sem `screens/mod-usage.html` e `screens/mod-moveto.html`**: uma lista simples e um
> select, inteiramente descritos pelos mapas (Tasks 18-19). Desenhá-los adicionaria superfície de kit
> sem decisão de design por trás. Não "completar" o conjunto.

---

# FASE 4 — README e sync

### Task 21: `README.md`

**Files:**
- Modify: `docs/design-kit/README.md`

- [ ] **Step 1: Atualizar a tabela de componentes — agora são 10**

Adicionar a linha do `sticky-footer.html` (grupo "Shell"): "As 3 variantes reais + o invariante —
é o lançador de ~10 dos 17 modais." (**17**, não 16 — contado na Task 3: 7 `Modal.create` +
5 `ModalSaveCancel` + 1 `ModalDeleteCancel` + 4 `ModalForm`.)

- [ ] **Step 1b: Registrar a exceção de nível do `modal-shell.html` (decidida na Task 3)**

O README (linhas 25-32) define dois níveis: **componentes** expressam só a visão to-be e divergem
da UI shipada de propósito; **`screens/`** é que reconcilia as-is ↔ to-be lado a lado. A Task 3
deixou o `modal-shell.html` — um componente — com **dois painéis**, ou seja, fazendo o papel do
nível de telas. **Isso é exceção deliberada, não descuido**, e o README precisa dizer:

> **Exceção — `modal-shell.html`.** É o único componente com painel as-is: o as-is do shell (o chip
> real do `.btn-close`, o slot de link no cabeçalho, a bifurcação do rodapé) não existe em nenhuma
> tela, e a proposta D2 é ilegível sem ver o cabeçalho de hoje ao lado do proposto. O shell é o
> único componente cujo as-is é disputado.

Sem essa linha, o índice do kit contradiz o arquivo.

- [ ] **Step 2: Atualizar a tabela de telas e a de mapas**

Adicionar `screens/mod-structrelated.html`, `maps/mod-usage.md`, `maps/mod-moveto.md`,
`maps/mod-structrelated.md`. Corrigir a afirmação "Todas as superfícies da Central estão cobertas" —
era falsa (`competency_usage_modal` contradizia o README:48).

- [ ] **Step 2b: Corrigir nomes mortos na tabela de telas (achados na varredura)**

`README.md:40` ainda anuncia `MOD.BROWSER` como *"Procurar em frameworks"* — o commit `f817430`
rebrandou as strings sem renomear as chaves: hoje é **"Procurar em estruturas"**, com rótulo
"Estrutura" e placeholder "Filtrar competências". Confira a tabela inteira contra as strings
shipadas; a inversão de nomes do hub (a aba de shortname `structure` renderiza "Competências",
enquanto `central_frameworks_tab` renderiza "Estruturas") torna esse tipo de erro fácil.

- [ ] **Step 3: Registrar as duas lacunas conhecidas**

Escrever uma seção nova. Ambas foram expostas pela auditoria e hoje ninguém sabe que existem:

```markdown
## Lacunas conhecidas

- **O kit não mapeia nenhum corpo de `dynamic_form`.** Por isso o lado *formulário* do `MOD.SCALE`
  (select congelado, paridade de escala nativa, atalho do cabeçalho de escalas — `a2112fe`,
  `8ab5635`, `c8901c0`, todos em `classes/form/framework_dynamic_form.php`) é invisível, mesmo com o
  modal coberto. Vale para os 4 ModalForms (framework, import, competência, template).
- **Os toasts viraram o padrão de confirmação da casa** (`addToastRegion(modal.getBody()[0])` no
  `ModalEvents.shown`, wired em `competency_links`, `participants_manager`, `related_competencies`,
  `frameworks_export`) mas só aparecem em `mod-related`/`mod-links`. A região é hospedada no corpo do
  modal porque a `.toast-wrapper` da página é `z-index:1051` e o modal é `1055`.
```

- [ ] **Step 4: Atualizar a nota de estado**

A linha "Próximo: **Camada 3**…" está obsoleta (a Camada 3 fechou). Substituir pelo estado real:
kit ressincronizado com `f84d30a`, com as melhorias do mtube mapeadas como to-be, e o backlog de
código do spec.

- [ ] **Step 5: Commit**

```bash
git add docs/design-kit/README.md
git commit -m "docs(kit): update the index and record the two blind spots

The README claimed every Central surface was covered while competency_usage_modal
had no map at all, and its 'next: layer 3' note outlived layer 3.

Records the two gaps the audit surfaced that nobody would otherwise know about:
the kit maps no dynamic_form body, which is why the form side of MOD.SCALE is
invisible, and toasts became the house confirmation pattern while appearing in
one screen."
```

---

### Task 22: Sync com o Claude Design

**Files:** nenhum local — escrita remota no projeto `35784af0-29b9-434f-b3f0-9618fa749829`

- [ ] **Step 0: Conferir o charset NO HOST — e não pôr `<meta charset>` nos cards**

Levantado e depois **corrigido** na Task 2. O achado bruto: servido por HTTP sem `charset` no
`Content-Type`, o Chrome cai para **windows-1252** e o card inteiro mojibaka — inclusive o nome
acessível que a Task 2 criou (`"Carregando competÃªnciasâ€¦"`). Não é cosmético: corrompe a AX name.

**Mas a conclusão óbvia é uma armadilha.** Os 9 cards são **fragmentos** — todos começam em
`<!-- @dsCard`, nenhum tem doctype/`<html>`/`<head>`/`<body>`; o harness os injeta num documento
hospedeiro. **`<meta charset>` dentro de fragmento é inerte** (o charset precisa estar nos
primeiros 1024 bytes do *documento*). Ou seja: "os cards não declaram charset" é verdade e é um
erro de categoria — nunca foi deles declarar. Adicionar a meta seriam **9 edições sem efeito**.

O que fazer: abrir um card **acentuado** no painel do Claude Design e olhar. Se os acentos
estiverem certos, o host já manda `charset=utf-8` e **não há bug**. Se mojibakear, o defeito é do
**wrapper do host / do `Content-Type` da rota de serviço** — reporte, não remende os fragmentos.

- [ ] **Step 1: Diff estrutural antes de escrever**

```
DesignSync method=list_files projectId=35784af0-29b9-434f-b3f0-9618fa749829
```

Comparar com `ls docs/design-kit/**/*.html`. Esperado: **1 arquivo novo** (`sticky-footer.html`) +
**1 tela nova** (`screens/mod-structrelated.html`); o resto é atualização. Os `maps/*.md` **não
sobem** (convenção: ficam só no repo). `README.md` e `moodle-ds-alignment.md` sobem (já estão lá).

- [ ] **Step 2: Finalizar o plano de escrita**

```
DesignSync method=finalize_plan
  projectId=35784af0-29b9-434f-b3f0-9618fa749829
  localDir=/Volumes/N1TB/dev/github/moodle/public/local/dimensions/docs/design-kit
  writes=["*.html","*.md","screens/*.html"]
  deletes=[]
```

`finalize_plan` exige `writes` **e** `deletes`, mesmo vazio. Guardar o `planId`.

- [ ] **Step 3: Escrever com `localPath`**

```
DesignSync method=write_files planId=<planId> files=[{path:"tokens.html", localPath:"tokens.html"}, …]
```

Usar **sempre `localPath`** (relativo ao `localDir`), nunca `data` inline — o conteúdo não entra no
contexto do modelo. Máx. 256 arquivos por chamada; aqui são ~22, uma chamada basta.

- [ ] **Step 4: Verificar o resultado**

```
DesignSync method=list_files projectId=35784af0-29b9-434f-b3f0-9618fa749829
```

Confirmar que `sticky-footer.html` e `screens/mod-structrelated.html` aparecem, e que nenhum
`maps/*.md` subiu por engano.

- [ ] **Step 5: Relatar ao usuário**

Não há commit aqui (a escrita é remota). Relatar: quantos cards subiram, quais são novos, e o link do
projeto para ele revisar visualmente — a validação final do kit é o olho dele, não um grep.

---

## Self-review deste plano

**Cobertura do spec:** Seção 1 → Tasks 1-5. Seção 2 → Tasks 6-17. Seção 3 → Tasks 18-20 (+ o
`sticky-footer.html` na Task 4). Seção 4 → Tasks 21-22. Worklist de 39: 26 as-is → Tasks 6-17, 21;
4 to-be → Tasks 1,2,3,5; 5 criações → Tasks 4,18,19,20; 4 no-change → Task 16. Backlog de código
(IMP-04, IMP-09, IMP-11, empty states) fica **fora** por decisão D1 e está registrado no spec.

**Consistência de nomes:** `--mds-motion-fast`/`-base`/`-flash`, `--mds-motion-ease`,
`--mds-loading-min-height` — mesmos nomes na Task 1 (definição), Task 2 (consumo em `states.html`) e
Task 3 (consumo no `modal-shell.html`). IDs: `MOD.USAGE`, `MOD.MOVETO`, `MOD.STRUCTRELATED` seguem o
prefixo do README. `EST-DETAIL-MOVEUP`/`MOVEDOWN` aposentados em **dois** lugares que se cruzam
(Tasks 7 e 19) — proposital, é uma ref cruzada, não duplicação.

**Sem placeholder:** todo passo de conteúdo traz o texto/markup real ou o comando exato de derivação.
Onde o conteúdo é grande demais para o plano (as telas de 90-390 linhas), o passo traz o **delta
exato + a citação**, que é o que o executor não consegue derivar sozinho — o HTML ele escreve a
partir do arquivo existente, que já está no formato certo.

**Risco conhecido:** a Task 7 (`est-structure.html`, 387 linhas, 4 divergências) é a maior e a mais
provável de precisar de segunda passada. Executá-la cedo na Fase 2, como está.
