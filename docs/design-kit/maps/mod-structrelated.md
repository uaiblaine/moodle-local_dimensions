# Mapa de Campos — `MOD.DETAIL` · O card é o diálogo (as-is)

A superfície mais incomum do plugin: um modal **sem cabeçalho**, em que **o card de detalhe da
competência _é_ o diálogo**. Não há casca do core visível — nem título, nem barra, nem `.btn-close`,
nem rodapé. O `core/modal` vira um contêiner transparente de 620px e tudo o que o usuário vê é o
mesmo card que a aba Estrutura mostra no painel inline, flutuando sobre o backdrop, com um botão de
fechar **próprio**, desenhado dentro do corpo e pintado em JS com a cor de texto da competência.

É também o único lugar do kit onde o leitor **não consegue inferir a construção sem abrir três
arquivos** — o Mustache não diz que o cabeçalho some, o JS não diz por que o `title` é passado, e o
CSS não diz quem aplica a classe. Por isso **o contrato de CSS mora aqui**: é o único lugar onde ele
seria escrito.

> **Nome do arquivo × nome do ID — leia isto antes de procurar `MOD.STRUCTRELATED`.**
> O arquivo se chama `mod-structrelated.md` porque o template se chama `structure_related_modal`.
> O **prefixo é `MOD.DETAIL`**, e não `MOD.STRUCTRELATED`, porque **o modal já tinha nome**: o
> [`pln-plans.md`](pln-plans.md) o batizou em `:234` (`MOD.DETAIL` ← `competency_detail.js:277`) e o
> referencia de novo em `:186`. Emitir um segundo nome para o mesmo diálogo é exatamente o defeito
> que a convenção de IDs proíbe (o caso `MOD.BROWSER-ACTION` × `PLN-BROWSE`), e deixaria a referência
> do `pln-plans.md` apontando para o nada. **O nome do template é que envelheceu**, não o ID: ele
> nasceu como alvo dos chips da Estrutura (`47677dd`) e **um dia depois** virou o card compartilhado
> das duas abas (`a59d5fb`, que extraiu o `competency_detail.js` e tirou 261 linhas do `structure.js`).
> Hoje "structure related" descreve **uma** das duas portas.

- **Mustache:** [`structure_related_modal.mustache`](../../../templates/central/structure_related_modal.mustache)
  (46, a casca headless) + [`structure_detail_content.mustache`](../../../templates/central/structure_detail_content.mustache)
  (126, **o partial compartilhado com o painel inline** — é o que faz os dois visuais serem idênticos
  por construção, não por coincidência)
- **AMD:** [`competency_detail.js`](../../../amd/src/central/competency_detail.js) (297) —
  `openCompetencyDetailModal` em `:265-297`; `renderDetailInto` (`:220-226`), `nodeToDetailData`
  (`:235-253`), `applyHeaderColors` (`:121-137`), `darkenHex` (`:102-112`). Importa `core/modal`
  (`:29`) e `local_dimensions/collapsible_description` (`:34`)
- **WS:** `local_dimensions_get_structure_node` (`db/services.php:125-126`) — **sempre busca o nó
  fresco**, mesmo quando o chamador já tem os dados na linha (ver a regra 4)
- **CSS:** [`styles.css:5147-5199`](../../../styles.css) — o contrato inteiro (tabela abaixo); mais a
  **exclusão** em `:3557`, `:3571`, `:3581-3582`, e o card herdado em `:4300-4304` (raio 24px) e
  `:4310-4316` (o gradiente 140deg)
- **Behat:** nenhum
- **Tela no DS:** [`screens/mod-structrelated.html`](../screens/mod-structrelated.html) — **um painel
  as-is só**: nada está proposto para mudar

**Abreviações usadas nas tabelas:** `js:` = `amd/src/central/competency_detail.js` · `mustache:` =
`templates/central/structure_related_modal.mustache` · `detail:` =
`templates/central/structure_detail_content.mustache` · `css:` = `styles.css`. Caminhos que começam
com `lib/` são do **core**, relativos a `public/`.

## A armadilha de nomes — respondida de forma direta

Há **dois** modais com "related" no nome, e eles são **coisas diferentes**:

| | `MOD.RELATED` ([`mod-related.md`](mod-related.md)) | **`MOD.DETAIL`** (este) |
| --- | --- | --- |
| **O que é** | o **gerenciador** de relações: lista, remove, adiciona pela árvore | o **card** da competência referenciada, como diálogo |
| **Abre por** | `EST-DETAIL-RELATED` — o botão ⇄ do **sticky-footer** (`structure_footer_actions.mustache:57-60`) | os **chips** (`MOD.RELATED-CHIP`) e os **nomes** da aba Planos (`PLN-COMP-NAME`) |
| **Módulo** | `related_competencies.js:248` | `competency_detail.js:277` |
| **Cabeçalho do core** | **visível** (título "Competências referenciadas — {nome}") | **oculto** (`css:5158-5160`) |
| **Carrega `.local-dimensions-related-modal`** | **não** | **sim** — `js:285` |

**Então: quem carrega a classe `.local-dimensions-related-modal` é este modal — o card headless.** Ela
é aplicada em `js:285` (`root.addClass('local-dimensions-related-modal')`), **depois** do
`Modal.create`, na raiz retornada por `modal.getRoot()`. O `MOD.RELATED`, apesar do nome quase igual
e de ser *o* modal "de referenciadas", **não** a carrega.

**E o que o `:not()` protege?** O restyle de `.btn-close` do plugin (`css:3550-3586`) pinta um chip
azul-claro com um "×" azul-escuro em **todo** modal que tenha conteúdo do plugin no corpo. O seletor é
`.modal:not(.local-dimensions-related-modal):has(.modal-body [class*='local-dimensions-']) .btn-close`
— ou seja, ele **exclui deste modal** o restyle que aplica a todos os outros. O comentário do
`css:3554-3555` diz o motivo: *"the referenced-competency modal keeps its own close button (its
header is hidden), so it is excluded"*.

**Ressalva medida, porque o comentário quase se contradiz:** hoje a exclusão **não protege nada que
seja pintado**. O `.btn-close` do core mora dentro do `.modal-header` (`lib/templates/modal.mustache:51`,
dentro do `:46`), e esse cabeçalho é `display: none` (`css:5158-5160`). Um ancestral `display: none`
apaga a subárvore inteira — declarar `display: inline-flex` no descendente não a ressuscita. O
`.btn-close` deste modal **nunca renderiza, com ou sem o `:not()`**; e o botão do corpo tem classe
própria (`.local-dimensions-related-modal-close`), que o seletor não alcança. A exclusão é, portanto,
**intenção declarada + seguro** para o dia em que o cabeçalho deixar de ser oculto — não um mecanismo
ativo. Registrado como **redundância deliberada**, não como erro: o parêntese do próprio comentário
("its header is hidden") é a razão de ela ser redundante.

## O contrato de CSS — `css:5147-5199`

Cinco regras transformam um `core/modal` comum no card. **Nenhuma tem `!important`**; todas vencem
por especificidade de classe.

| Alvo | Declaração | Origem | Por quê |
| --- | --- | --- | --- |
| `.modal-dialog` | `max-width: 620px` | `css:5150-5151` | mais estreito que o `modal-lg` — **e o sobrepõe**: o `js:280` passa `large: true`, que põe `.modal-lg` (800px) no diálogo, e `.local-dimensions-related-modal .modal-dialog` (0,2,0) ganha de `.modal-lg` (0,1,0). O `large: true` é **letra morta** |
| `.modal-dialog` | `border-radius: 24px` | `css:5155` | **não é decoração** — o diálogo é transparente, então nada dessa borda aparece. Ela existe **só pelo anel de foco**: o `core/modal` foca o `.modal-dialog` ao abrir (`lib/amd/src/modal.js:899` → `getModal().focus()`, e o `tabindex="0"` em `lib/templates/modal.mustache:44`). Sem o raio, o anel sairia **retangular em volta de um card arredondado**. Os 24px casam com o raio do card (`css:4301`), e o comentário `css:5153-5154` diz isso |
| `.modal-header` | `display: none` | `css:5158-5160` | some com título **e** `.btn-close` do core de uma vez |
| `.modal-content` | `border: 0` · `background: transparent` · `box-shadow: none` | `css:5162-5166` | apaga a casca: o que dá fundo, borda e sombra é o **card** (`css:4302-4303`) |
| `.modal-body` | `padding: 0` | `css:5168-5170` | o card encosta na borda do diálogo — é o que faz o anel de foco "abraçar" o card |

## Casca headless

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.DETAIL-MODAL` | — | `core/modal` | `js:277-283` | `title`, `body`, `large: true`, `show: true`, `removeOnClose: true` | `core/modal` **puro** — sem `footer`. A classe que dispara todo o contrato entra **depois**, em `js:285` |
| `MOD.DETAIL-TITLE` | {shortname} | título | `js:278` | `title: data.name` | **nunca pinta** (o cabeçalho é `display: none`) — **mas não é código morto**: continua sendo o **nome acessível** do diálogo. Medido, não deduzido — ver a regra 1 |
| `MOD.DETAIL-CARD` | `[sem rótulo]` | região/raiz | `mustache:36` | **três** classes: `.local-dimensions-central-plans-detail` + `.local-dimensions-central-structure-detail` + `.local-dimensions-related-modal-card` | as duas primeiras são **emprestadas**: trazem o raio de 24px, o fundo, a sombra (`css:4300-4304`) e o gradiente do cabeçalho (`css:4310-4316`) já prontos do painel inline. A terceira é só dele: `position: relative` (âncora do botão de fechar) + `overflow: hidden` (clipa o gradiente nos cantos arredondados) — `css:5172-5175` |
| `MOD.DETAIL-CLOSE` | Fechar | botão | `mustache:37-40` | `data-action="close-related-modal"` · `aria-label` = str core `closebuttontitle` · `fa fa-times` | **mora no corpo, não no cabeçalho** — é o substituto do `.btn-close`. `css:5177-5194`: `position: absolute` a 18px do topo/direita, 36×36, `z-index: 3`, fundo `rgba(255,255,255,0.16)` e borda `rgba(255,255,255,0.28)` — um "vidro" sobre o gradiente. A **cor** é escrita em JS (`js:294`): `data.textcolor` da competência, com **fallback `'#fff'`**. O listener é direto no elemento (`js:295`), não delegado |
| `MOD.DETAIL-CONTENT` | `[sem rótulo]` | contêiner-JS | `mustache:41-45` | `data-region="detail-content"` | onde o partial entra. O `js:286` o localiza na raiz do modal e **desiste calado** se não achar (`:287-289`) |

## Conteúdo — o partial compartilhado, com dois flags desligados

O corpo do card **não tem IDs próprios**: é o `structure_detail_content.mustache` inteiro, o **mesmo**
partial do painel inline da aba Estrutura, cujos elementos já são `EST-DETAIL-*` no
[`est-structure.md`](est-structure.md). Este mapa **não os re-emite**. O que muda aqui é o contexto:
`{linksclickable: false, showrelated: false}` (`js:275`), e ele muda **duas** coisas visíveis.

| Elemento (dono) | No painel inline | **Neste modal** | Mecanismo |
| --- | --- | --- | --- |
| `EST-DETAIL-COURSES` · `-ACTIVITIES` · `-PLANS` | `<button data-action="show-usage">` → abre o `MOD.USAGE` | `<div>` **inerte** — número sem clique | `{{#linksclickable}}` / `{{^linksclickable}}` (`detail:78-86`, `:90-98`, `:102-110`). **É o que impede empilhar um modal sobre este** |
| `MOD.RELATED-CHIPS` (a seção ⇄ de referenciadas) | renderiza, com contador e chips | **não existe no DOM** | `{{#showrelated}}` (`detail:116-125`). Por isso `populateRelated` (`structure.js:477-503`) sai calado quando a região não está lá |
| Cabeçalho, chips, descrição | idênticos | idênticos | mesmo `renderDetailInto` (`js:220-226`) |

**A consequência de design:** não há referenciadas dentro de referenciadas, e não há uso dentro de
detalhe. O card é uma **folha** — abre, informa, fecha. Nenhum modal empilha sobre ele.

## Portas de entrada — **duas, nenhuma nova aqui**

| ID (dono) | Aba | Origem | Caminho |
| --- | --- | --- | --- |
| `MOD.RELATED-CHIP` | Estrutura ([`mod-related.md`](mod-related.md)) | `structure_related_chips.mustache:36-43` | `data-action="open-related"` + `data-id` → `structure.js:1244-1248` → `openCompetencyDetailModal(id)` |
| `PLN-COMP-NAME` | Planos ([`pln-plans.md`](pln-plans.md)) | `plans.mustache:381-382` | `data-action="open-competency-detail"` + `data-id` → `plans.js:754-755` → mesmo `openCompetencyDetailModal(id)` |

**Nenhuma das duas é rodapé** — e é o único modal do kit de que isso é verdade. Toda a Central abre
modal por botão de sticky-footer; este abre por **um chip** e por **um nome clicável no meio de uma
lista**. O `pln-plans.md:234` já registrava a observação.

## Regras de negócio (verificadas no código)

### 1. O título nunca pinta — e mesmo assim nomeia o diálogo (medido)

O `js:278` passa `title: data.name`. O cabeçalho é `display: none` (`css:5158-5160`), então **nada
disso aparece na tela**. A conclusão tentadora — "o `title` é código morto, pode sair" — está
**errada**.

O `core/modal` liga `aria-labelledby="{{uniqid}}-modal-title"` na raiz do diálogo
(`lib/templates/modal.mustache:43`) apontando para o `<h5 id="{{uniqid}}-modal-title">` do `:49` — que
está **dentro** do cabeçalho oculto. Pela AccName, um nó oculto **diretamente referenciado** por
`aria-labelledby` **entra** no cálculo do nome acessível.

**Medido em Chromium** (árvore de acessibilidade real, com controles positivo e negativo, sobre uma
réplica da estrutura do `core/modal` + a regra do plugin):

- **caso real** (cabeçalho `display:none`, `aria-labelledby` → `h5` lá dentro): a subárvore do
  cabeçalho aparece como `ignored` — o `h5` **não chega à árvore** — e mesmo assim o diálogo sai como
  **`dialog "Comunicação Assertiva" modal`**. **Nomeado.**
- **controle positivo** (mesmo diálogo, cabeçalho visível): `dialog "Visible Title Control" modal`.
- **controle negativo** (`aria-labelledby` apontando para um id inexistente): `dialog modal` — **sem
  nome**, provando que a ferramenta mostra a ausência quando ela existe.

Ou seja: **o `title` é a única coisa que nomeia este diálogo para leitor de tela.** Tirá-lo deixaria o
card visualmente idêntico e o diálogo anônimo. É a razão de o `MOD.DETAIL-TITLE` ter ID mesmo sem
pintar um pixel.

### 2. O anel de foco é o motivo do raio de 24px

Sem o `border-radius: 24px` do `css:5155`, nada mudaria de aparência — o `.modal-dialog` é
transparente (`css:5162-5166`) e não tem borda visível. A regra existe por causa de **um** momento: o
`core/modal` chama `getModal().focus()` ao abrir (`lib/amd/src/modal.js:899`) e o `.modal-dialog`
carrega `tabindex="0"` (`lib/templates/modal.mustache:44`). O anel de foco do navegador segue o
`border-radius` do elemento focado. Como o `.modal-body` tem `padding: 0` (`css:5168-5170`), o
diálogo tem **exatamente** o tamanho do card — e o anel precisa ter **exatamente** o raio do card
(`css:4301`, 24px) para não desenhar um retângulo em volta de um card redondo. O comentário do
`css:5153-5154` registra o raciocínio; este mapa registra que ele **depende de duas linhas do core**.

### 3. A cor do fechar vem do dado, não do tema

`js:294`: `closebtn.style.color = data.textcolor || '#fff'`. O `textcolor` é o campo personalizado da
competência (via `nodeToDetailData`, `js:246`), o mesmo que pinta o texto do cabeçalho
(`applyHeaderColors`, `js:136`). O botão é um "vidro" translúcido (`rgba(255,255,255,0.16)` sobre o
gradiente, `css:5190`), então o glifo tem de acompanhar o texto do cabeçalho ou destoa.

**O risco que isso cria:** o `textcolor` é livre. Uma competência com `textcolor` escuro sobre um
`bgcolor` escuro produz um "×" ilegível — e **não há guarda**: nem contraste calculado, nem fallback
condicional (o `|| '#fff'` só cobre o **vazio**, não o ilegível). O mesmo vale para o cabeçalho
inteiro, então não é regressão deste modal; é a política de cor do plugin, e aqui ela alcança o
**único** controle do diálogo. Ver a medição na tela.

### 4. O modal sempre refaz a busca, mesmo com o dado na mão

`openCompetencyDetailModal` (`js:265`) recebe **só o id** e chama `local_dimensions_get_structure_node`
(`js:266-269`) antes de qualquer render. Os dois chamadores **já têm** dados: o chip nasce de uma
linha da árvore cujo `dataset` tem tudo o que o `renderDetailInto` pede, e a aba Planos idem.

É deliberado, e a razão é o `nodeToDetailData` (`js:235-253`): o card precisa de `coursecount`,
`activitycount`, `templatecount`, `ruletype`, `rulelabel` e `haschildren` — números que a **linha de
origem não carrega** (o chip só tem `id` e `name`, `structure_related_chips.mustache:38`). Buscar o nó
fresco é o que permite as duas portas usarem o **mesmo** código. O custo: um round-trip por abertura,
sem cache. Se o nó sumiu, sai calado — `if (!response.found || !response.node) return` (`js:270-272`)
—, sem toast e sem modal: o clique simplesmente não faz nada.

### 5. `removeOnClose` é o que sustenta as guardas de render

`removeOnClose: true` (`js:282`) destrói a árvore ao fechar. Por isso o guard das renderizações
assíncronas é `() => modalcontent.isConnected` (`js:291`) — e não uma flag: quando os `getString` dos
chips e o `renderForPromise` da descrição voltam (`js:156-158`, `:196-205`), o teste é se o nó ainda
está **no documento**. Fechar rápido não deixa "chip fantasma" nem exceção; o `applyChipText`
(`js:88-93`) simplesmente não escreve. O comentário do `js:290` diz isso em uma linha.
