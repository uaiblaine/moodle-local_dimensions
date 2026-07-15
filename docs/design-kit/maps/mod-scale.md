# Mapa de Campos — `MOD.SCALE` · Escala/proficiência do framework (as-is)

Editor da configuração de proficiência de uma escala: **uma linha por valor da escala**, com um rádio
"padrão" e um checkbox "proficiente". É um **modal `core/modal_save_cancel` próprio** (zero YUI),
aberto pelo botão **Configurar escala** que o `framework_dynamic_form` desenha — ou seja, **um modal
em cima de um `ModalForm`**. Lê os valores da escala no core e resolve o JSON
`scaleconfiguration` do core (`[{scaleid}, {id, scaledefault, proficient}, …]`), que **quem chamou**
grava num campo oculto do formulário.

- **Mustache:** [`framework_scaleconfig.mustache`](../../../templates/central/framework_scaleconfig.mustache)
  (51, só as linhas — **não** a casca do modal) · gatilho em
  [`framework_dynamic_form.php`](../../../classes/form/framework_dynamic_form.php) (`:191-195`), como
  **string de HTML crua** num elemento `static`
- **AMD:** [`framework_scaleconfig.js`](../../../amd/src/central/framework_scaleconfig.js) (155) —
  `open` em `:125-155`, `serialize` em `:56-68`, `isComplete` em `:76-90`, `parseExisting` em `:40-47`,
  `buildRows` em `:99-116`. Fiado por [`frameworks.js`](../../../amd/src/central/frameworks.js):
  `openScaleConfigForForm` (`:62-86`) e `setupScaleConfigDelegation` (`:95-123`)
- **WS: uma, do core.** `core_competency_get_scale_values` (`js:129-132`). **Nenhum WS do plugin** — o
  modal não tem entrada em `db/services.php`
- **Tela no DS:** [`screens/mod-scale.html`](../screens/mod-scale.html) (as-is ↔ to-be, com o título
  real e a validação dirigida)

**Abreviações usadas nas tabelas:** `js:` = `amd/src/central/framework_scaleconfig.js` · `mustache:` =
`templates/central/framework_scaleconfig.mustache` · `frameworks.js:` =
`amd/src/central/frameworks.js` · `form.php:` = `classes/form/framework_dynamic_form.php`.

> **Resync 2026-07-15 — as 4 refs deste mapa estão TODAS certas, e mesmo assim a primeira frase dele
> negava o que ele é. O mapa se chama `MOD.SCALE` — `MOD` de modal — e dizia que não havia modal.**
> Medido, não estimado:
>
> - **4 refs; 4 corretas (4/4).** Cada linha citada contém o elemento que o mapa diz. O motivo é o
>   mesmo do `mod-rule.md` e do `mod-links.md`: o `framework_scaleconfig.mustache` **não é tocado desde
>   `283e9a7` (2026-06-28)**, o commit que criou o modal, e o mapa nasceu em `159a800` (**2026-06-29**).
>   **Ref limpa não é mapa certo** — este arquivo é a terceira prova disso na série.
> - **"Renderizado client-side dentro do form de criar/editar framework" está errado.** O `js:139` é
>   `const modal = await ModalSaveCancel.create({title, body: html})`: as linhas são o **corpo de um
>   modal nativo**, não conteúdo injetado no formulário. O `framework_scaleconfig.mustache` entrega só
>   as linhas porque a **casca é do core** — foi disso que o mapa antigo tirou a conclusão errada. A
>   **tela estava certa** e o mapa errado, o inverso do resto da série.
> - **É um modal _em cima de outro modal_.** O `FWK-NEW` (`fwk-frameworks.md:55`) e o `FWK-ROW-EDIT`
>   (`:82`) abrem o `framework_dynamic_form` num `ModalForm` (`frameworks.js:28`, `:174`); o botão
>   **Configurar escala** mora **dentro** desse formulário e abre este `ModalSaveCancel` por cima. É
>   por isso que a fiação é delegada no `document` em fase de **captura** — ver "O gatilho".
> - **Zero refs de JS.** Um `grep -oE '[a-z_/.]+\.(php|js|mustache|css):[0-9]+(-[0-9]+)?'` no arquivo
>   antigo devolve **4**, todas em `framework_scaleconfig.mustache`. Este e o `mod-rule.md` eram os
>   **dois únicos** dos 12 mapas do kit sem nenhuma ref de `.js`.
> - **A validação inteira não existia no mapa.** `isComplete` (`js:76-90`) + `event.preventDefault()`
>   + `Notification.alert('', incomplete)` (`js:145-148`) são o único ponto em que este modal diz
>   "não", e o mapa antigo não os mencionava. E o **idioma é outro**: aqui é um **popup**
>   (`Notification.alert`), enquanto o irmão `MOD.RULE` usa um alerta **inline**
>   (`[data-region="error"]`). Dois modais do mesmo hub, criados com um dia de diferença, resolvem o
>   mesmo problema de dois jeitos. Ver "A validação".
> - **O `MOD.SCALE-HEAD` estava rotulado "Valor da escala" — a string é "Valor".**
>   `central_frameworks_scalevalue` = `'Value'` / `'Valor'` (`lang/en:156`, `lang/pt_br:156`). O rótulo
>   era a chave parafraseada. A tela repetia o texto no painel as-is (`:48`) — e o painel **to-be**
>   (`:63`) já escrevia "Valor", certo por acidente. Corrigido nos dois.
> - **Não havia ID para o título.** O modal tem um (`central_frameworks_configurescale` = "Configurar
>   escala", `js:135`), e a tela desenhava **"Novo framework · escala de proficiência"** — texto que
>   não existe em lugar nenhum do código.
> - **O to-be apontava para `form-section.html`**, que é o card *"Seção de formulário · Título +
>   descrição + linhas de campo"* — a escolha seguia da mesma premissa errada (que isto era um trecho
>   de formulário). Sendo modal, a casca é o `modal-shell.html`.

## O gatilho — nasce no formulário, e o formulário não tem mapa

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.SCALE-ACTION` | Configurar escala | botão (gatilho) | `form.php:191-195` | `data-action="configure-scale"` · `.btn.btn-secondary.btn-sm` | str `central_frameworks_configurescale` — **a mesma str do título do modal** (`js:135`), como no `MOD.RULE`/`EST-DETAIL-RULES`. **ID provisória**: pela convenção da casa o gatilho pertence à superfície onde mora, e ele mora no corpo do `framework_dynamic_form` — que **nenhum mapa do kit cobre**. Deve migrar quando o mapa do formulário existir; ver "A lacuna dos `dynamic_form`". Não é duplicata: um `grep -rn 'MOD\.SCALE' docs/design-kit/` não devolve nenhuma outra ID para este botão |
| `MOD.SCALE-SUMMARY` | Configurada / `[vazio]` | texto | `form.php:194` (nó), `:189-190` (valor inicial) | `data-region="scaleconfig-summary"` · `.text-muted.small.ms-2` | str `central_frameworks_scaleconfigured` = "Configurada". **É a única coisa que o `$configured` muda.** O `form.php:195` adiciona o botão **incondicionalmente** — o `$configured` (`:189`, via `helper::scaleconfig_is_complete`) só escolhe o texto do resumo (`:190`). Consequência: **o botão existe no caminho de criar**, não só no de editar, e este modal **não depende do sticky-footer** de jeito nenhum. Depois do salvar, quem escreve o resumo é o `frameworks.js:77-82` |
| `MOD.SCALE-HIDDEN` | `[sem rótulo]` | campo oculto | `form.php:186-187` | `name="scaleconfiguration"` · `PARAM_RAW` | **o destino real do modal.** O `open` resolve uma string e o `frameworks.js:76` a grava aqui; é o formulário que persiste, no seu próprio salvar. Se o usuário fechar o `ModalForm` sem salvar, a configuração escolhida **se perde** — o modal de escala não escreve nada no servidor |

**A fiação é global, e o comentário no código diz por quê.** `setupScaleConfigDelegation`
(`frameworks.js:95-123`) registra **uma vez** (`scaleconfigwired`, `:96-99`) um listener no
`document` em **fase de captura** (`:107`, o `true`). Os dois docblocks explicam (`:88-92`, `:100-101`):
o formulário renderiza dentro de um `ModalForm` cujo ciclo de vida **não roda o `init` do plugin**, e
a captura garante que o clique seja visto na descida, antes que qualquer coisa dentro do modalform o
interrompa.

**Os seletores são por `name`, nunca por `id`** (`frameworks.js:65-67`), e o comentário `:63-64` dá a
razão: `core_form\dynamic_form` sufixa os ids com um aleatório (`id_scaleid_c5fLCIS8ExDrcVf`), então
um `#id_scaleid` fixo nunca casaria. O mesmo achado está no `fwk-frameworks.md:137`.

**Trocar a escala apaga a configuração — exceto se o select estiver congelado.** O handler de
`change` (`frameworks.js:108-122`) zera o `MOD.SCALE-HIDDEN` e o `MOD.SCALE-SUMMARY`, porque a
configuração antiga aponta para ids de valores de outra escala. Mas ele sai antes se o select tem
`readonly` (`:109`) — e o comentário `:110-111` diz o motivo: um framework já avaliado tem a escala
congelada, e o servidor fixa o `scaleid` por constante de formulário; apagar a configuração ali seria
destruir dado por um evento que o usuário não pode nem disparar.

## Casca do modal

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.SCALE-TITLE` | Configurar escala | título | `js:135` (str), `:139` (`ModalSaveCancel.create`) | str `central_frameworks_configurescale` | `lang/en:123` = `'Configure scale'`; `lang/pt_br:123` = `'Configurar escala'`. **Sem nome de escala nem de framework** — o `title` é a string crua. As duas strings do modal (título e erro) são pedidas **em paralelo** num `Promise.all` (`js:134-137`). `setRemoveOnClose(true)` em `:140` |
| `MOD.SCALE-SAVE` | Salvar | botão (rodapé) | `lib/templates/modal_save_cancel.mustache` | `data-action="save"` | vem com o `ModalSaveCancel` (import `js:28`). **É o único ponto de validação** — ver "A validação" |
| `MOD.SCALE-CANCEL` | Cancelar | botão (rodapé) | `lib/templates/modal_save_cancel.mustache` | `data-action="cancel"` | fechar por Cancelar, pelo X ou por ESC cai no `ModalEvents.hidden` (`js:152`) → `resolve(null)` → o `frameworks.js:73-75` vê `null` e **não toca** no campo oculto nem no resumo |
| `MOD.SCALE-NOSCALE` | `[nada — o modal não abre]` | guarda | `js:126-128` | `if (!scaleid) { return null; }` | escala não escolhida → o `open` devolve `null` **sem abrir nada e sem avisar**. O clique em "Configurar escala" simplesmente não faz nada. Registrado, não consertado |

## Corpo — uma linha por valor da escala

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.SCALE-HEAD` | **Valor** · Padrão · Proficiente | cabeçalho | `mustache:34-38` | strs `central_frameworks_scalevalue` (`:35`) / `scaledefault` (`:36`) / `scaleproficient` (`:37`) | `lang/pt_br:156` = **"Valor"** — **não** "Valor da escala". As outras duas: `:153` = "Padrão", `:155` = "Proficiente". Larguras fixas em estilo **inline** (`4rem` / `5rem`), não em classe |
| `MOD.SCALE-ROW` | {nome do valor} | linha | `mustache:40` (linha), `:41` (nome) | `data-value="{{id}}"` · `.d-flex` | uma por valor, do `buildRows` (`js:99-116`). **O `data-value` é o contrato inteiro com o AMD**: `serialize` (`js:58`) e `isComplete` (`js:79`) varrem `[data-value]` e leem `row.dataset.value` (`js:62`). Sem tabela — são `div`s em flex |
| `MOD.SCALE-DEFAULT` | `[só aria-label]` — "{nome} Padrão" | rádio | `mustache:43-44` | `name="dimensions-scaledefault"` · `data-role="default"` · `value="{{id}}"` | **o `name` é fixo e literal**, e é ele que garante "exatamente um padrão": o agrupamento é do rádio nativo, não do JS. O `aria-label` (`:44`) concatena o nome do valor + a str do cabeçalho — é o único nome acessível, já que a coluna não tem `<label>` |
| `MOD.SCALE-PROFICIENT` | `[só aria-label]` — "{nome} Proficiente" | checkbox | `mustache:47-48` | `data-role="proficient"` · `value="{{id}}"` · **sem `name`** | **um ou mais** proficientes — não há agrupamento, cada linha é independente. Ao contrário do rádio, **não tem `name`**; nada o lê por `name`, só por `data-role` (`js:60`, `:81`) |

**As pré-seleções vêm de um JSON que perde o primeiro elemento de propósito.** `parseExisting`
(`js:40-47`) faz `JSON.parse` e `.slice(1)` (`:43`) — o formato do core guarda `{scaleid}` na posição
0 e as configurações por valor depois. O `try/catch` (`:41-46`) devolve `[]` em JSON inválido, então
uma configuração corrompida abre o modal **vazio** em vez de estourar. `buildRows` (`:99-116`) vira
os dois vetores em mapas (`defaults`/`proficients`) e casa **por id do valor**, não por posição — um
valor removido da escala simplesmente não aparece.

## A validação — popup, não inline

`isComplete` (`js:76-90`) varre as linhas e devolve `hasdefault && hasproficient` (`:89`). No salvar
(`js:144-151`):

```
if (!isComplete(root)) {
    event.preventDefault();                    // js:146 — segura o modal aberto
    Notification.alert('', incomplete);        // js:147 — popup POR CIMA do modal
    return;
}
resolve(serialize(root, scaleid));             // js:150
```

A str é `central_frameworks_scaleincomplete` = **"Selecione pelo menos um valor padrão e um valor de
proficiência."** (`lang/pt_br:154`). O primeiro argumento do `alert` é `''` — o popup **não tem
título**.

**A divergência de idioma com o `MOD.RULE` é o achado que vale registrar.** Os dois modais nasceram
com um dia de diferença (`a78c3f6` 27/06 e `283e9a7` 28/06), os dois validam no `ModalEvents.save`, os
dois chamam `event.preventDefault()` para segurar o diálogo — e aí divergem:

| | `MOD.RULE` | `MOD.SCALE` |
| --- | --- | --- |
| Onde o erro aparece | alerta **inline** no corpo (`data-region="error"`) | **popup** `Notification.alert` por cima |
| Quem o desliga | **ninguém** — é _write-once_ | o usuário, fechando o popup |
| Tem título | — | **não** (`''`, `js:147`) |
| Empilhamento | nenhum | **um terceiro nível**: popup > modal de escala > modalform |

Nenhum dos dois é o padrão da casa para feedback em modal (o toast hospedado no corpo, que o
`mod-links.md` documenta) — mas os dois são **erros de validação bloqueantes**, não confirmações, e
para isso o toast seria o veículo errado. Fica registrada a **inconsistência entre os dois**, não uma
correção: uniformizar mexe em comportamento shipado de dois modais.

## O que o salvar resolve

`serialize` (`js:56-68`) monta o formato do core à mão:

```
const config = [{scaleid: Number(scaleid)}];                       // js:57 — a posição 0 que o parseExisting descarta
root.querySelectorAll('[data-value]').forEach((row) => {           // js:58
    config.push({id, scaledefault: def && def.checked ? 1 : 0,     // js:61-65 — 1/0, não booleano
                 proficient: prof && prof.checked ? 1 : 0});
});
```

Os `1`/`0` (não `true`/`false`) são o que o core espera. O `def &&` / `prof &&` protege contra linha
sem os controles — situação que o Mustache não produz, mas que o `serialize` não assume.

Quem recebe é o `frameworks.js:71-85`: `null` (cancelar) → sai (`:73-75`); string → grava no
`MOD.SCALE-HIDDEN` (`:76`) e escreve "Configurada" no `MOD.SCALE-SUMMARY` (`:77-82`). Erro de rede vai
para `notifyError` (`:85`). **Nada é persistido aqui** — o `scaleconfiguration` só chega ao banco
quando o `framework_dynamic_form` for salvo.

## A lacuna dos `dynamic_form` — não é deste mapa

O `MOD.SCALE-ACTION`, o `MOD.SCALE-SUMMARY` e o `MOD.SCALE-HIDDEN` moram nos `form.php:186-195`, no
corpo de um `core_form\dynamic_form` — e **o kit não mapeia nenhum corpo de `dynamic_form`**. Um
`ls classes/form/` devolve **quatro** (`framework_`, `import_framework_`, `competency_`,
`template_dynamic_form.php`) e nenhum tem tela ou mapa; os maps só os citam de passagem
(`fwk-frameworks.md:82`, `pln-plans.md:293-298`).

É por isso que **este modal está limpo e o `MOD.SCALE` como assunto não está**: os três commits de
julho da escala — `a2112fe` (atalho no cabeçalho de escalas + paridade com a escala nativa),
`8ab5635` (congelar o select de escala) e `c8901c0` (select congelado não tropeça na regra de
obrigatório) — caíram **todos** no `framework_dynamic_form.php`, nenhum aqui. O lado **formulário** do
`MOD.SCALE` é território não coberto. **Registrado para o README, não consertado aqui.**

## to-be — o estado vira pill, o rótulo vira o real

O painel to-be mantém as duas IDs (`MOD.SCALE-DEFAULT`/`-PROFICIENT`) e troca o rádio/checkbox crus
por pills legíveis — "inicial" e "✓ proficiente" —, que dizem **o que o estado significa** em vez de
pedir que o usuário deduza da coluna. O cabeçalho usa a str real ("Valor").

**"Inicial" é proposta de rótulo**, não transcrição: a str shipada é "Padrão"
(`central_frameworks_scaledefault`), e é ela que o painel **as-is** desenha. A tela marca a diferença
na sua própria nota — a mesma disciplina que o `mod-rule` aplica ao "Quando".

**O as-is agora é dirigido.** O controle ao pé do painel é real (`<input type="checkbox">` + `:has()`,
o precedente do `mod-delplans`, **sem JS**): desmarca o único proficiente e salva → o `isComplete`
(`js:145`) reprova e o **popup** sobe por cima do modal, com a **barra de título vazia** que o
`Notification.alert('', incomplete)` de fato produz. O terceiro nível de empilhamento (popup > modal
de escala > modalform) fica visível em vez de descrito.

**O que o to-be não conserta:** a validação por popup, o `MOD.SCALE-NOSCALE` silencioso e o
`scaleconfiguration` que só persiste com o formulário são de `.js`/`.php`, não de layout.

## Resumo das divergências as-is ↔ mapa/tela antigos

| O que o mapa/tela antigos diziam | O que está no ar |
| --- | --- |
| "Linhas inline … renderizado dentro do form de criar/editar framework" | **`ModalSaveCancel.create`** (`js:139`) — modal próprio, **por cima de um `ModalForm`** |
| `MOD.SCALE-HEAD` = "Valor da escala" | a str `central_frameworks_scalevalue` é **"Valor"** (`lang/pt_br:156`) |
| Título "Novo framework · escala de proficiência" (tela `:46`) | str `central_frameworks_configurescale` = **"Configurar escala"** (`js:135`) — e é a **mesma str** do botão que abre |
| Nenhuma menção à validação | `isComplete` (`js:76-90`) → `preventDefault` + **popup sem título** (`js:145-148`), str `central_frameworks_scaleincomplete` |
| Nenhuma ref de `.js` (0 de 4) | 5 comportamentos mapeados: gatilho delegado, título, pré-seleção, validação, serialização |
| To-be "parcial em `form-section.html`" | é modal → a casca é o `modal-shell.html`; o `form-section.html` é *"Seção de formulário"* |
| 4 controles, todos de Mustache | 11 IDs — 3 no formulário (provisórias), 4 na casca, 4 no corpo |
