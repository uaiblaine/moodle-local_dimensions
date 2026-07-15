# Mapa de Campos — `MOD.RULE` · Regra de conclusão (as-is)

Editor da regra de conclusão de uma competência, aberto pelo `EST-DETAIL-RULES` — o botão **Regra de
competência** do **sticky-footer** da aba Estrutura. São três controles em cascata: **resultado**
(o que acontece ao completar), **tipo de regra** e, só para a regra por **pontos**, uma tabela de
pontos por filha com um total exigido. O modal **não fala com o servidor**: recebe as filhas e os
tipos de regra prontos do `structure.js`, e resolve um objeto `{ruletype, ruleoutcome, ruleconfig}`
que **quem chamou** persiste.

- **Mustache:** [`rule_config.mustache`](../../../templates/central/rule_config.mustache) (98) ·
  gatilho em [`structure_footer_actions.mustache`](../../../templates/central/structure_footer_actions.mustache)
  (`:49-52`)
- **AMD:** [`rule_config.js`](../../../amd/src/central/rule_config.js) (186) — `show` em `:138-186`,
  `readPointsConfig` em `:115-128`, `buildContext` em `:82-107`. Importado por
  [`structure.js`](../../../amd/src/central/structure.js) (`:36`), que abre (`:895`) e persiste (`:896`)
- **WS: nenhum.** Este módulo não chama `Ajax` — um `grep -n "core/ajax" amd/src/central/rule_config.js`
  devolve **nada**. As duas escritas (`core_competency_read_competency` + `core_competency_update_competency`)
  são do `structure.js`, em `persistRule` (`:849-868`), e são **do core**: o modal não tem entrada em
  `db/services.php`
- **Tela no DS:** [`screens/mod-rule.html`](../screens/mod-rule.html) (as-is ↔ to-be, com o erro
  dirigido e o título real)

**Abreviações usadas nas tabelas:** `js:` = `amd/src/central/rule_config.js` · `mustache:` =
`templates/central/rule_config.mustache` · `structure.js:` = `amd/src/central/structure.js`.
Caminhos que começam com `admin/` ou `lang/` são do **core**, relativos a `public/`.

> **Resync 2026-07-15 — as 11 refs deste mapa estão TODAS certas, e mesmo assim ele descrevia o
> modal errado: rotulava um controle com um texto que não é a string dele, e não mapeava nenhuma das
> quatro coisas que o AMD faz.** Medido, não estimado:
>
> - **11 refs; 11 corretas (11/11).** Cada linha citada foi lida e **contém** o elemento que o mapa
>   diz — o teste que reprovou as Tasks 10, 12 e 14. O motivo é mecânico e já foi registrado no
>   `mod-links.md`: o `rule_config.mustache` **não é tocado desde `a78c3f6` (2026-06-27)**, o commit
>   que criou o modal, e o mapa nasceu em `159a800` (**2026-06-29**). Ref que aponta para arquivo
>   parado não apodrece. **A previsão de que este mapa estaria limpo checou a coisa errada.**
> - **Zero refs de JS — o mesmo defeito do `mod-links.md`, e aqui ele apaga o comportamento inteiro.**
>   Um `grep -oE '[a-z_/.]+\.(php|js|mustache|css):[0-9]+(-[0-9]+)?'` no arquivo antigo devolve **11**,
>   **todas** em `rule_config.mustache`, nenhuma em `.js` — e este e o `mod-scale.md` eram os **dois
>   únicos** dos 12 mapas do kit sem nenhuma ref de JS (`grep -rln '\.js:[0-9]' maps/` devolvia os
>   outros 10). O mapa antigo resumia o AMD numa frase de prosa (*"alterna a visibilidade do
>   tipo/pontos e lê os valores no salvar"*) e não citava **nada**: nem o título, nem a cascata, nem a
>   validação, nem a regra que decide o erro.
> - **O `MOD.RULE-TYPE-LABEL` estava rotulado "Tipo de regra" — a string é "Regra".** O mapa antigo
>   dava o rótulo *e* a chave (`central_rule_type`) na mesma linha, e os dois não batem:
>   `lang/en:275` e `lang/pt_br:275` dizem `'Rule'` / `'Regra'`. O rótulo era a chave parafraseada,
>   não a string. A tela repetia o mesmo texto (`:51`). Corrigido nos dois.
> - **O `MOD.RULE-ERROR` não dizia quando dispara**, e a condição real tem **dois** ramos, não um:
>   `js:124` é `requiredpoints < 1 || total < requiredpoints`. A tela só desenhava o segundo
>   (*"quando o total > soma"*, `:63`) e ignorava o primeiro. Ver "A validação".
> - **O erro era _write-once_ — o mapa antigo não dizia, e o defeito foi CORRIGIDO em 2026-07-15**
>   (`d343716`). **Era:** um `grep -n 'errorEl\|showerror'` no módulo devolvia **3** linhas e só **uma**
>   escrevia (`errorEl.hidden = false`); o `refresh` não tocava no alerta, então uma vez visível ele
>   ficava — inclusive depois de o usuário trocar o resultado para **Nenhum** e a tabela de pontos
>   inteira sumir. **É:** o mesmo grep devolve **4** linhas e **duas** escrevem — `js:178` liga o
>   alerta, `js:158` o apaga junto com a tabela que ele descreve. Ver "A validação".
> - **O `showerror` do Mustache é sempre `false`.** `buildContext` (`js:89`) o fixa em `false` e nada
>   mais o escreve — o `{{^showerror}}hidden{{/showerror}}` (`mustache:68`) portanto **sempre**
>   renderiza `hidden`. A variável não é um estado do servidor: existe só para semear o atributo, e
>   quem liga o alerta é o JS em tempo de execução. O mapa antigo a listava como se fosse condição de
>   render.
> - **O to-be apontava para um card que não tem o controle citado.** O mapa antigo dizia *"To-be no
>   DS: parcialmente em `paginated-picker.html` (controle `ruleoutcome`)"*. Um
>   `grep -n 'ruleoutcome\|rule' docs/design-kit/paginated-picker.html` devolve **nada** — o arquivo
>   existe (133 linhas) e é o *"Picker paginado · Busca server-side + resultados AJAX + paginação"*, que
>   não tem nem regra nem outcome. A ref era falsa. A casca certa é o `modal-shell.html`.
> - **O gatilho não ganha ID nova aqui.** O `est-structure.md:137` já mapeia `EST-DETAIL-RULES` em
>   `structure_footer_actions.mustache:49-52` e já diz "abre `MOD.RULE`". Este mapa **referencia**, em
>   vez de cunhar um `MOD.RULE-ACTION` — a convenção que o `MOD.DELPLANS` ← `PLN-DELETE` estabeleceu.

## Gatilho (na aba Estrutura, fora do modal)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `EST-DETAIL-RULES` | Regra de competência | botão (gatilho) | `structure_footer_actions.mustache:49-52` — ID de [`est-structure.md`](est-structure.md) (`:137`) | `data-action="rules"` · `fa fa-list` | str `competencyrule, tool_lp` — **a mesma str do título do modal** (`js:140`), então o botão e o diálogo que ele abre têm rótulo idêntico. É o mesmo padrão do `PLN-DELETE`/`MOD.DELPLANS-TITLE`, e aqui é literalmente a mesma chave nos dois lados. Mora no **sticky-footer** compartilhado da aba, não numa linha. `structure.js:887-898` (`showRuleConfig`) lê as quatro `data-*` da linha ativa (`:888-893`), busca as filhas e chama `show(competency, children, rulesModules)` (`:895`) |
| `EST-JSON-RULES` | `[sem rótulo]` | dados JSON | `structure.mustache:95` — ID de [`est-structure.md`](est-structure.md) (`:33`) | `data-region="rules-modules"` | **os tipos de regra vêm do servidor, não do JS**: `readJson` (`structure.js:123-133`) os lê no init (`:1353`) e o vetor atravessa o `show` até o `buildContext` (`js:97-101`). Um tipo novo no core aparece aqui sem tocar no AMD |

## Casca do modal

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.RULE-TITLE` | Regra de competência | título | `js:140` (str), `:144` (`ModalSaveCancel.create`) | str `competencyrule, tool_lp` | `admin/tool/lp/lang/en/tool_lp.php:71` = `'Competency rule'`. **Sem nome de competência**: o `create` recebe `{title, body}` (`:144`) e o `title` é a string **crua** — nada concatena o alvo, ao contrário do `MOD.LINKS-TITLE`, que tem `$a`. `ModalSaveCancel` (import `:28`), então o rodapé já vem com Cancelar + Salvar e não há `setSaveButtonText`. `setRemoveOnClose(true)` em `:145` |
| `MOD.RULE-ROOT` | `[sem rótulo]` | região/raiz | `mustache:51` | `data-region="rule-config"` · `.local-dimensions-rule-config` | wrapper do corpo. **Os cinco `querySelector` do módulo (`js:147-151`) partem do root do _modal_** (`modal.getRoot()[0]`, `:146`), não deste nó — o `data-region="rule-config"` é, hoje, gancho sem leitor: um `grep -n 'rule-config' amd/src/ styles.css` fora do `build/` não devolve nenhum uso. Registrado, não removido |
| `MOD.RULE-SAVE` | Salvar | botão (rodapé) | `lib/templates/modal_save_cancel.mustache` | `data-action="save"` | vem de graça com o `ModalSaveCancel`. **É o único ponto de validação do modal** — ver "A validação" |
| `MOD.RULE-CANCEL` | Cancelar | botão (rodapé) | `lib/templates/modal_save_cancel.mustache` | `data-action="cancel"` | fechar por Cancelar, pelo X ou por ESC cai todo no `ModalEvents.hidden` (`js:183`) → `resolve(null)` → o `structure.js:896` vê `null` e **não persiste** |

## Corpo — a cascata

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.RULE-OUTCOME-LABEL` | Resultado | rótulo | `mustache:53` | str `outcome, tool_lp` · `for="local-dimensions-rule-outcome"` | `<label>` de verdade, com `for` |
| `MOD.RULE-OUTCOME` | Resultado (select) | select | `mustache:54` | `data-region="outcome"` · `.form-select` | **o topo da cascata**. 4 opções, do `OUTCOMES` (`js:37-42`): `0` Nenhum · `1` Anexar uma evidência · `3` Recomendar a competência · `2` Marcar como concluída — **a ordem do vetor não é a ordem numérica** (`0,1,3,2`), e é ela que o usuário vê. Rótulos são strs do **core** (`competencyoutcome_*`, `tool_lp:66-69`), pedidas em lote (`getStrings`, `js:139`) e casadas **por índice** (`js:92-96`), não por chave — reordenar `OUTCOMES` sem reordenar nada mais continua correto, mas trocar o `getStrings` por um lote com outra ordem embaralharia os rótulos calado. `.form-select` (nunca `custom-select`) |
| `MOD.RULE-TYPE-WRAP` | — | wrapper | `mustache:60` | `data-region="ruletype-wrap"` | nasce `hidden` se `{{^hasrule}}` — `hasrule` é `ruleoutcome !== 0` (`js:87`). Depois do render quem manda é o `refresh` (`js:155`) |
| `MOD.RULE-TYPE-LABEL` | **Regra** | rótulo | `mustache:61` | str `central_rule_type` · `for="local-dimensions-rule-type"` | `lang/en:275` = `'Rule'`; `lang/pt_br:275` = `'Regra'`. **Não é "Tipo de regra"** — era o rótulo inventado do mapa antigo. A string é do **plugin**, não do `tool_lp`: o core não expõe uma equivalente |
| `MOD.RULE-TYPE` | Regra (select) | select | `mustache:62` | `data-region="ruletype"` · `.form-select` | opções do `EST-JSON-RULES`, via `rulesModules` (`js:97-101`); `value` = a **classe** do core (`core_competency\competency_rule_all`, `…_points`). Só o `…_points` (`RULE_POINTS`, `js:34`) abre a tabela |
| `MOD.RULE-ERROR` | O total de pontos disponíveis deve ser ao menos igual aos pontos necessários. | alerta **inline** | `mustache:68-70` (nó), `js:178` (liga), `js:158` (apaga) | `data-region="error"` · `role="alert"` · `.alert-danger` | str `central_rule_invalidpoints`. Nasce **sempre** `hidden` (o `showerror` é fixo em `false`, `js:89`). Ligado só no salvar inválido (`js:178`) e apagado pelo `refresh` (`js:158`) junto com a tabela de pontos que ele descreve — era _write-once_ até 2026-07-15; ver "A validação". É irmão **acima** do `MOD.RULE-POINTS` (`mustache:68-70` vs `:71`), não filho: por isso esconder a tabela nunca levou a mensagem junto sozinho. Idioma **inline**; o irmão `MOD.SCALE` usa um `Notification.alert` de popup para o mesmo papel |
| `MOD.RULE-POINTS` | — | wrapper | `mustache:71` | `data-region="points"` | nasce `hidden` se `{{^ispoints}}` — e **`ispoints` (`js:88`) não olha o `hasrule`**, enquanto o `refresh` (`js:156`) olha os dois. Ver "A divergência do primeiro render" |
| `MOD.RULE-POINTS-TABLE` | tabela de pontos | tabela | `mustache:73` | `.table.table-sm` | só sob `{{#haschildren}}` (`:72`) = `children.length > 0` (`js:90`). **Competência-folha não tem tabela** — o wrapper existe vazio, e um salvar com regra de pontos cai no `requiredpoints < 1` (não há input) → erro. Ver "A validação" |
| `MOD.RULE-POINTS-HEAD` | `[col 1 sem rótulo]` · Pontos · Obrigatório | cabeçalho | `mustache:74-79` | strs `points, tool_lp` (`:77`) / `required` (`:78`) | a 1ª coluna (nome da filha) é um `<th scope="col">` **vazio** (`:76`). O `required` é do **core** (`lang/en/moodle.php:1848`), sem componente; o `points` é do `tool_lp` (`:183`) |
| `MOD.RULE-POINTS-ROW` | linha por filha | linha | `mustache:83-87` | `data-competency="{{id}}"` · `input[name=points]` · `input[name=required]` | nome em `<th scope="row">` (`:84`); `points` é `number` com `min="0"` (`:85`); `required` é checkbox (`:86`). **O contrato inteiro com o AMD é o par `data-competency`/`name`** (`js:118-121`) — sem classe nem id intermediando |
| `MOD.RULE-POINTS-TOTAL` | Total necessário para concluir | linha | `mustache:89-93` | str `totalrequiredtocomplete, tool_lp` · `input[name=requiredpoints]` | `tool_lp:272`. `number` com `min="1"` (`:91`) — **e o `min` do HTML não protege nada aqui**: não há submit de formulário para o navegador validar, o salvar é um botão de modal. Quem barra o `0` é o `js:124`. É a linha da tabela que **não** é uma filha: fica dentro do mesmo `<tbody>`, com a 3ª célula vazia (`:92`) |

## A validação — o único ponto onde o modal diz "não"

`readPointsConfig` (`js:115-128`) é chamado **só** no ramo de pontos (`js:175`) e é a única coisa que
pode devolver `null`:

```
const total = competencies.reduce((sum, comp) => sum + Math.max(0, comp.points), 0);   // js:123
if (requiredpoints < 1 || total < requiredpoints) { return null; }                      // js:124
```

**São dois ramos, e o mapa/tela antigos só conheciam o segundo:**

1. **`requiredpoints < 1`** — o total exigido é `0` (ou vazio: `Number(input.value || 0)`, `js:117`).
   O `min="1"` do markup não impede digitar `0`. **É também o ramo da competência-folha**: sem
   `{{#haschildren}}` não há `[name="requiredpoints"]` no DOM, o `js:116-117` cai no ternário e
   `requiredpoints` vira `0` → erro. O usuário vê um alerta falando de pontos num modal **sem tabela
   de pontos**.
2. **`total < requiredpoints`** — a soma das filhas não alcança o exigido. O `Math.max(0, …)` (`:123`)
   descarta ponto negativo antes de somar, então o `min="0"` do `MOD.RULE-POINTS-ROW` também é
   redundante como proteção.

O caminho inválido faz **duas** coisas e nada mais (`js:176-179`):

```
event.preventDefault();     // js:177 — segura o modal aberto
errorEl.hidden = false;     // js:178 — liga o alerta inline
```

O `preventDefault()` é o que impede o `registerCloseOnSave` do core de destruir o diálogo — é o
contraponto exato do `MOD.DELPLANS`, onde a **ausência** dessa chamada faz o modal fechar antes da
escrita voltar. Aqui o estado precisa sobreviver, e sobrevive.

**O alerta era _write-once_ · CORRIGIDO em 2026-07-15** (`d343716`).

**Era:** `js:178` (`errorEl.hidden = false`) era a **única** escrita em `errorEl` no arquivo inteiro, e
o `refresh` só mexia em `ruletypeWrap.hidden` e `pointsEl.hidden`. Uma vez ligado, o alerta ficava
ligado **até o modal ser destruído** — inclusive depois de o usuário trocar o resultado para
**Nenhum**, o que esconde a tabela de pontos inteira e deixava na tela um alerta sobre pontos que não
estavam mais visíveis. Salvar nesse estado **funcionava** (o ramo `outcome === 0`, `js:166-169`,
resolve antes de qualquer validação) e o modal fechava com o alerta ainda aceso, tendo avisado sobre
nada.

**É:** o `refresh` (`js:153-159`) termina apagando o alerta junto com a tabela que ele descreve:

```
errorEl.hidden = errorEl.hidden || pointsEl.hidden;   // js:158
```

Os **dois** caminhos que tiram o assunto do alerta escondem a tabela — resultado = **Nenhum** e tipo
de regra ≠ pontos, os dois ramos do `js:156` —, então derrubá-lo por `pointsEl.hidden` cobre ambos sem
um segundo teste.

**Apaga _condicionalmente_, e isto é a decisão, não um detalhe de escrita.** Enquanto a tabela está na
tela o alerta **continua verdadeiro**: trocar o resultado entre dois resultados **reais** (p. ex.
*Anexar uma evidência* → *Marcar como concluída*) mantém a tabela visível e deixa os mesmos pontos
inválidos no lugar. Um `errorEl.hidden = true` no topo do `refresh` — a "correção de uma linha" que
este mapa chegou a propor — apagaria um veredito que **ainda vale**, fazendo um formulário inválido
parecer limpo. O `errorEl.hidden ||` é exatamente o que preserva o veredito.

O `grep -n 'errorEl\|showerror'` no módulo agora devolve **4** linhas (eram 3) e **duas** escrevem:
`js:178` liga, `js:158` apaga. O alerta é irmão **acima** da região de pontos (`mustache:68-70` vs
`:71`), não filho dela — é por isso que esconder a tabela nunca levou a mensagem junto por markup, e
por isso a correção é de `.js`.

## Os três desfechos do salvar

O handler (`js:164-182`) escuta `ModalEvents.save` e tem **três** saídas, nesta ordem:

| Ordem | Condição | Resolve | Nota |
| --- | --- | --- | --- |
| 1 | `outcome === OUTCOME_NONE` (`js:166`) | `{ruletype: null, ruleoutcome: 0, ruleconfig: null}` | **limpa a regra inteira** — o `ruletype` vai a `null` junto com o outcome, mesmo que o select de tipo mostre "Pontos". É o que impede o estado `ruletype` sem `ruleoutcome` de nascer por aqui |
| 2 | `ruletype !== RULE_POINTS` (`js:171`) | `{ruletype, ruleoutcome: outcome, ruleconfig: null}` | regra "todas as filhas" e afins não têm config — `ruleconfig` **é** `null`, não `'{}'` |
| 3 | pontos (`js:175`) | `{ruletype, ruleoutcome, ruleconfig: <json>}` ou **erro** | o JSON é `{base: {points: requiredpoints}, competencies: [...]}` (`js:127`) — o formato do core, montado à mão |

Quem recebe é o `structure.js:896`, que só chama `persistRule` se o valor **não** for `null`
(cancelar) — e o `persistRule` (`:847-879`) lê a competência inteira do core (`read_competency`,
`:849`), reenvia **todos** os campos com os três de regra trocados (`:850-868`), e então **grava o
estado na linha da árvore + pisca**, sem recarregar o pane (`:872-876`, o comentário em `:871` diz
exatamente isso). É o mesmo par toast+flash do `MOD.LINKS`.

## A divergência do primeiro render

O Mustache e o `refresh` **não usam a mesma regra** para a tabela de pontos:

| Quem | Regra | Ref |
| --- | --- | --- |
| Mustache (1º render) | `{{^ispoints}}hidden` → visível quando `ruletype === points` | `mustache:71`, `js:88` |
| `refresh` (todo o resto) | `!(hasrule && ruletype === points)` → visível quando **as duas** | `js:156` |

`refresh` **não roda no init** — só é ligado ao `change` dos dois selects (`js:160-161`). Então uma
competência com `ruletype = points` **e** `ruleoutcome = 0` abriria com a tabela de pontos visível e
o select de tipo escondido, até o primeiro `change`. **O caminho é estreito**: o desfecho 1 do salvar
(acima) zera os dois juntos, então o par não nasce por este modal. Fica registrado como divergência
entre as duas fontes da mesma verdade, não como bug observado.

## Contraste — medido no alerta que a tela agora desenha

O `MOD.RULE-ERROR` do as-is é o par `--text-danger`/`--bg-danger` do kit. Medido no DOM com o alerta
**ligado** (o estado que o `js:178` produz), animações canceladas antes de ler:

| Par | Tema | Razão | Veredito |
| --- | --- | --- | --- |
| `#a32d2d` sobre `#fcebeb` (texto do alerta) | claro | **6,13:1** | passa |
| `#f09595` sobre `#2a1313` (texto do alerta) | escuro | **7,86:1** | passa |
| `#fcebeb` sobre `#fff` (preenchimento) | claro | **1,15:1** | tinta decorativa — ver abaixo |
| `#f09595` sobre `#fff` (borda) | claro | **2,23:1** | falha 3:1 (não-texto) |
| `#2a1313` sobre `#26252a` (preenchimento) | escuro | **1,15:1** | tinta decorativa |
| `#791f1f` sobre `#26252a` (borda) | escuro | **1,47:1** | falha 3:1 |

**A superfície tem de ser lida do ancestral pintado, não do pai.** O pai imediato do alerta (`.m-body`)
é `rgba(0, 0, 0, 0)` — medir contra ele devolve `18,22:1` / `9,44:1`, números que **parecem ótimos e
não significam nada** (transparente lido como preto). Subindo até o primeiro ancestral com
preenchimento real (`.m` = `#fff`) saem os `1,15` / `2,23` da tabela. Fica registrado porque a
leitura errada é silenciosa e favorável.

**A tinta e a borda fracas não são reprovação aqui:** quem carrega o significado é o **texto** (que
passa nos dois temas) mais o `role="alert"` (`mustache:68`), que o entrega ao leitor de tela sem cor
nenhuma. O preenchimento é reforço. As bordas fracas são o caso conhecido do kit
(`--border-strong`/`--border-stronger` reprovam 3:1 em todas as superfícies recentes) e **não** são
consertadas aqui.

## to-be — resumo em uma frase, e o erro onde o olho está

O painel to-be da tela mantém as três IDs (`MOD.RULE-OUTCOME`/`-TYPE`/`-POINTS`) e muda três coisas:

- **Resultado e regra lado a lado**, porque são um par que se lê junto — hoje são duas linhas
  empilhadas de largura fixa.
- **A regra vira uma frase** ("precisa de 2 de 2 pontos"), calculada do mesmo `total` que o `js:123`
  já soma. É o número que decide o erro, mostrado **antes** do erro.
- **"Quando" no lugar de "Regra"** — e isto é **proposta de rótulo**, não transcrição: o resultado
  responde *o quê*, a regra responde *quando*. A str shipada é "Regra" (`central_rule_type`), e é
  ela que o painel **as-is** desenha. A tela diz qual é qual na sua própria nota, para a proposta não
  ser lida como o as-is — foi exatamente essa confusão que produziu o "Tipo de regra" do mapa antigo.

**O as-is agora é dirigido.** Os dois controles ao pé do painel são reais (`<input type="checkbox">` +
`:has()`, o precedente do `mod-delplans`, **sem JS**): o primeiro troca o total exigido para 3, pinta
o campo de perigo e liga o alerta — o ramo `total < requiredpoints` do `js:124`; o segundo esconde a
regra e a tabela, o que o `refresh` faz com resultado = Nenhum.

> **Resync pendente na tela (2026-07-15).** O segundo driver desenha o alerta **ficando aceso** —
> `screens/mod-rule.html:87` ("e o alerta fica (write-once, js:176)") e o comentário em `:51` ("NAO
> toca no alerta"). Esse é o comportamento **anterior** ao `d343716`: hoje o `refresh` apaga o alerta
> junto com a tabela (`js:158`), então o painel dirigido desenha um as-is que não está mais no ar.
> Medido no arquivo da tela nesta data, não presumido. **O mapa é a fonte; a tela ainda não foi
> resincada** — e o achado que ela dramatizava deixou de existir, então o driver precisa mudar de
> comportamento, não só de rótulo.

**O que o to-be não conserta e a tela não finge que conserta:** a divergência do primeiro render é de
`.js`, não de layout.

## Resumo das divergências as-is ↔ mapa/tela antigos

| O que o mapa/tela antigos diziam | O que está no ar |
| --- | --- |
| `MOD.RULE-TYPE-LABEL` = "Tipo de regra" | a str `central_rule_type` é **"Regra"** (`lang/pt_br:275`) |
| Título "Regra de conclusão · Resolução de problemas" (tela `:48`, `:72`) | str **core** `competencyrule, tool_lp` = "Regra de competência", **sem** nome de competência (`js:140`, `:144`) — e é a **mesma str** do botão que abre |
| Erro "quando o total > soma" | **dois** ramos: `requiredpoints < 1 \|\| total < requiredpoints` (`js:124`) — o 1º também pega a competência-folha |
| Erro nomeado `Erro "pontos inválidos"` | a str `central_rule_invalidpoints` = "O total de pontos disponíveis deve ser ao menos igual aos pontos necessários." |
| `showerror` como condição de render | é **sempre `false`** (`js:89`); quem liga o alerta é o JS (`js:178`) — e quem o desliga é o `refresh` (`js:158`), desde `d343716` (2026-07-15); até lá **nada** o desligava |
| Nenhuma ref de `.js` (0 de 11) | 4 comportamentos mapeados: título, cascata, validação, desfechos do salvar |
| To-be "parcialmente em `paginated-picker.html` (controle `ruleoutcome`)" | o card **não tem** `rule` nem `ruleoutcome` (`grep` devolve nada em 133 linhas); a casca é o `modal-shell.html` |
| 11 controles, todos de Mustache | 15 IDs + 2 gatilhos reusados (`EST-DETAIL-RULES`, `EST-JSON-RULES`) |
