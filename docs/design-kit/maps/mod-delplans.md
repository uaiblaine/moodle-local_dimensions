# Mapa de Campos — `MOD.DELPLANS` · Excluir template com planos (as-is)

Confirmação de exclusão de um template de plano de aprendizagem, aberta pelo `PLN-DELETE` — o botão
**Excluir modelo** do **sticky-footer** da aba Planos. São **dois diálogos, não um**: o `plans.js`
pergunta ao core se o template tem dados relacionados e, **só se tiver**, renderiza o modal próprio —
que nomeia o template, mostra a **contagem real** de planos e soletra a **consequência de cada
escolha** (desvincular, padrão; ou excluir os planos). Sem planos, o fluxo cai num
`deleteCancelPromise` do core. O rádio marcado vira o argumento `deleteplans` de
`core_competency_delete_template`.

- **Mustache:** [`delete_template_modal.mustache`](../../../templates/delete_template_modal.mustache)
  (73) — **na raiz de `templates/`, não em `templates/central/`**; é o **único** modal deste kit fora
  do `central/`. Gatilho em [`plans.mustache`](../../../templates/central/plans.mustache) (`:481-485`)
- **AMD:** [`plans.js`](../../../amd/src/central/plans.js) — `deleteTemplate` em `:234-272`, despacho
  em `:746-748`. Importa `core/modal_delete_cancel` (`:27`); usa `errors.js` (`notifyError`) e
  `tabs.js` (`reloadPane`)
- **PHP:** [`plans.php`](../../../classes/output/dynamictabs/plans.php) `:319-321` exporta
  `selectedtemplateplancount` via `helper::count_plans_by_template`
- **WS:** core `core_competency_template_has_related_data` (`js:236-239`, **o gate**) e core
  `core_competency_delete_template` (`js:241-244`, escrever). **Nenhum WS do plugin** — os dois são do
  core, e por isso este modal não tem entrada em `db/services.php`
- **CSS:** [`styles.css`](../../../styles.css) `:5406-5478` — bloco próprio, **literais**, **sem
  variante dark** (o comentário `:5410-5414` explica: o corpo nasce fora do `.local-dimensions-manage`,
  então as custom properties do hub não estão no escopo)
- **Behat:** [`manage_plans.feature`](../../../tests/behat/manage_plans.feature) `:33-42` — cobre
  **só o caminho sem planos**; ver a nota de cobertura
- **Tela no DS:** [`screens/mod-delplans.html`](../screens/mod-delplans.html) (as-is ↔ to-be, com o
  gate dirigido e as duas opções reais — clicáveis e medidas)

**Abreviações usadas nas tabelas:** `js:` = `amd/src/central/plans.js` · `mustache:` =
`templates/delete_template_modal.mustache` · `plans.mustache:` =
`templates/central/plans.mustache`. Caminhos que começam com `lib/` são do **core**, relativos a
`public/`.

> **Resync 2026-07-15 — o mapa anterior inventariava um arquivo que não existe mais, e a tela dizia
> que o to-be não tinha sido construído. Ele foi — há duas semanas.** Medido, não estimado:
>
> - **3 refs; 3 quebradas (3/3) — mas por um motivo diferente do resto da série.** Um
>   `grep -oE '[a-z_/.]+\.(php|js|mustache|css):[0-9]+(-[0-9]+)?'` no mapa antigo devolve
>   **exatamente 3**, todas em `delete_template_plans.mustache`. Elas **não envelheceram por deriva:
>   estavam certas quando foram escritas**. Um
>   `git show 820a449^:templates/central/delete_template_plans.mustache` confirma que `:29` era mesmo
>   o `<p>` do `deletetemplatewithplans`, `:31` o rádio `value="0" checked` e `:37` o rádio
>   `value="1"`. O que aconteceu é que o **arquivo inteiro foi apagado** — o `820a449` o removeu (42
>   linhas, `-`) ao criar o substituto. As refs estão **órfãs**, não erradas; é a primeira vez nesta
>   série que a causa é deleção, e não drift.
> - **O to-be já é o as-is.** O `820a449` ("feat: explicit-consequence delete modal (mod-delplans
>   to-be), shared by both flows", 2026-07-01) implementou exatamente o painel "Proposta (to-be) ·
>   consequência explícita" que a tela desenhava: nome do template, contagem real e nota de
>   consequência por opção. A tela continuava anunciando isso como proposta.
> - **O caminho do arquivo mudou de pasta, não só de nome.** O mapa antigo apontava para
>   `templates/central/delete_template_plans.mustache`; o shipado é
>   `templates/delete_template_modal.mustache` — **na raiz**. Um `find templates -iname '*delete*'`
>   devolve **uma** linha, a da raiz. O motivo está no commit: o modal nasceu **compartilhado** entre
>   a Central e a antiga tela `manage_templates.js`, e por isso ficou fora do `central/`. **A razão já
>   não existe**: o `f804e14` ("refactor(admin): remove the legacy manage/edit admin surface",
>   2026-07-07) apagou o `amd/src/manage_templates.js`, e hoje um
>   `grep -rn 'delete_template_modal' --include='*.js' --include='*.php' --include='*.mustache' .`
>   (fora do `build/`) devolve **um só** renderizador: `plans.js:247`. **O template está na raiz por
>   um compartilhamento que acabou** — mover para `central/` é mudança de uma linha no
>   `renderForPromise`, e fica registrada aqui como dívida, fora do escopo desta tarefa.
> - **O gate nunca foi mapeado, e ele decide qual dos dois diálogos abre.** O mapa antigo descrevia o
>   modal como se fosse o único desfecho de "Excluir modelo". Não é: `js:236-239` chama
>   `core_competency_template_has_related_data` **antes** de qualquer render, e o `if (hasplans)`
>   (`:246`) escolhe. **Sem planos não há modal do plugin** — cai no `deleteCancelPromise` do core
>   (`:265-271`). Os dois caminhos agora estão desenhados na tela (storyboard dirigido) e nas tabelas
>   abaixo.
> - **Zero refs de JS, como em todos os mapas anteriores da série** — e aqui isso apagava o fluxo
>   inteiro. Nada em `.js` era citado: nem o gate, nem o título, nem a leitura do rádio, nem o
>   fallback, nem o despacho. **O mapa antigo cobria 3 controles; este cobre 12** (mais o `PLN-DELETE`
>   emprestado do mapa da aba) — contados com
>   `grep -oE '^\| \`MOD\.DELPLANS-[A-Z-]+\`' | sort -u | wc -l`.
> - **As IDs ganharam o prefixo `MOD.`.** O mapa antigo usava `DELPLANS-MSG`/`-UNLINK`/`-DELETE`
>   crus; o `README.md:72` define o prefixo como `MOD.{…,DELPLANS}` e os três vizinhos frescos
>   (`MOD.BROWSER-*`, `MOD.LINKS-*`, `MOD.RELATED-*`) já o usam. Normalizado aqui. `UNLINK` e `DELETE`
>   mantêm o sufixo (mesmo controle); **`DELPLANS-MSG` foi aposentado** — a mensagem genérica que ele
>   nomeava (`deletetemplatewithplans`, do `tool_lp`) não é mais renderizada, e um
>   `grep -rn 'deletetemplatewithplans'` no plugin (fora do `build/`) devolve **nada**. No lugar dela
>   entraram dois elementos com conteúdo próprio: `MOD.DELPLANS-NAME` e `MOD.DELPLANS-INPLANS`.
> - **Uma nota de Behat colada no diálogo errado.** O mapa antigo dizia "o diálogo casa pelo
>   **título** (`deleteCancelPromise`)". O caminho **com** planos não é um `deleteCancelPromise` — é
>   um `ModalDeleteCancel.create` (`js:251`). A observação só vale para o **fallback**, e é
>   justamente o que o Behat exercita. Ver a nota de cobertura.
> - **O gatilho não ganha ID nova aqui — ele já tem uma.** O mapa antigo dizia "Acionado por
>   `PLN-DELETE`", e isso **confere**: o `pln-plans.md:223` mapeia `PLN-DELETE` em
>   `plans.mustache:481-485`, exatamente a ref derivada aqui de forma independente, e o
>   `pln-plans.md:232` já publica o cruzamento `MOD.DELPLANS` ← `PLN-DELETE` **quando há planos**.
>   Este mapa **reusa** `PLN-DELETE` em vez de cunhar um `MOD.DELPLANS-ACTION`, para não dar duas IDs
>   ao mesmo botão. **Divergência registrada:** o `mod-browser.md` (Task 14) fez o contrário — cunhou
>   `MOD.BROWSER-ACTION` para `plans.mustache:469-472`, que o `pln-plans.md:220` já chama de
>   `PLN-BROWSE`; aquele botão hoje tem **duas** IDs. Os dois mapas não podem estar certos; fica para
>   decisão de quem mantém o kit.

## Gatilho (na aba Planos, fora do modal)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `PLN-DELETE` | Excluir modelo | botão (gatilho) | `plans.mustache:481-485` — ID de [`pln-plans.md`](pln-plans.md) (`:223`) | `data-action="delete-template"` · `data-id` · `data-name` · `data-plancount` · `fa fa-trash` | str `managetemplates_delete` = "Excluir modelo" — **a mesma str do título do modal** (`js:252`), então o botão e o diálogo que ele abre têm rótulo idêntico. Mora no holder `data-region="plans-footer-actions"` (`plans.mustache:462`), que nasce `hidden` e é movido para o `#sticky-footer` da página pelo `plans.js`; só sai sob `{{#canmanage}}` (`:457`). **O rodapé é a única porta** deste modal. Despacho em `js:746-748`, com `target.dataset.name \|\| ''` e `target.dataset.plancount \|\| 0` |

**A contagem já chega no clique.** O `data-plancount` (`plans.mustache:482`) é
`selectedtemplateplancount`, exportado no servidor por `plans.php:319-321`
(`helper::count_plans_by_template([$templateid])[$templateid] ?? 0` — a mesma fonte da pílula
`PLN-COUNT-PLANS`, `pln-plans.md:166`). O `js:249` faz `Number(plancount) || 0` e passa ao template.
Duas consequências que valem registro:

- **O WS do gate não traz o número.** `has_related_data` devolve booleano; quem sabe "12" é o
  servidor, do render anterior. O gate decide **o caminho**, nunca o texto.
- **O número pode estar velho.** Se alguém criou planos desde o último `reloadPane`, o modal mostra a
  contagem do render, não a do clique — enquanto o gate, esse sim, é consultado na hora. É possível
  (embora estreito) o gate dizer `true` e a contagem dizer `0`: o modal abriria com "Este modelo está
  em **0 planos** de alunos".

## O gate — qual dos dois diálogos abre

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.DELPLANS-GATE` | `[sem rótulo]` | regra (bifurcação) | `js:236-239` (WS), `js:246` (`if`) | `core_competency_template_has_related_data` | roda **antes** de qualquer render e é `await`ado — o clique não abre nada até o WS voltar, **sem spinner nem estado de espera** (a mesma lacuna que o IMP-03 descreve nos vizinhos; aqui ela é anterior ao modal, não dentro dele). `true` → o modal do plugin (`:247-262`, com `return` em `:262`); `false` → o `deleteCancelPromise` do core (`:265-271`). **Note a assimetria**: o gate pergunta por *related data*, não por *planos* — o nome do WS é do core e cobre mais do que planos, mas o modal que ele abre fala **só** de planos |

## Casca do modal (caminho **com** planos)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.DELPLANS-TITLE` | Excluir modelo | título | `js:252` (str), `:251-256` (`ModalDeleteCancel.create`) | str `managetemplates_delete` | é `ModalDeleteCancel` (`import` em `:27`), **não** `ModalSaveCancel` como o `MOD.BROWSER` nem `Modal` cru como o `MOD.RELATED` — o rodapé já vem com Cancelar + Excluir vermelho, e é por isso que este modal não tem nenhuma chamada de `setSaveButtonText`. `removeOnClose: true` **no config** (`:255`), não via setter. O `title:` recebe a **Promise** do `getString` **sem `await`** (`:252`) e isso é legal: `setTitle` (`lib/amd/src/modal.js:464-468`) delega ao `asyncSet` (`:1150`), que resolve promessas — o `body`, ao lado, é string já resolvida (`:247-250`) |
| `MOD.DELPLANS-ROOT` | `[sem rótulo]` | região/raiz | `mustache:40` | `.local-dimensions-delete-template-modal` | wrapper do corpo, **sem regra própria**: o `styles.css` estiliza as filhas (`:5415` em diante), nunca a classe do root. Mas ela não é gancho morto como a do `MOD.BROWSER-ROOT` — ver `MOD.DELPLANS-X` |
| `MOD.DELPLANS-CONFIRM` | Excluir | botão destrutivo (rodapé) | `lib/templates/modal_delete_cancel.mustache:44` | `data-action="delete"` · `.btn-danger` · str core `delete` | vem de graça com o `ModalDeleteCancel`; o plugin não o toca. **Está vermelho nas duas escolhas** — inclusive quando a marcada é "Desvincular", que não destrói nada. Handler em `js:257-261`; ver "O confirmar" |
| `MOD.DELPLANS-CANCEL` | Cancelar | botão (rodapé) | `lib/templates/modal_delete_cancel.mustache:43` | `data-action="cancel"` · str core `cancel` | `registerCloseOnCancel()` (`lib/amd/src/modal_delete_cancel.js:57`) fecha sem chamar nada |
| `MOD.DELPLANS-X` | Fechar | chip de fechar | core (`lib/templates/modal.mustache`) | — | ganha o restyle azul de `1.75rem` do hub (`styles.css:3557-3562`) pelo mesmo seletor dos vizinhos, que exige um `[class*='local-dimensions-']` no corpo. Aqui **quem casa é o `MOD.DELPLANS-ROOT`** — e, ao contrário do `MOD.BROWSER`, não há um segundo candidato: todas as classes do corpo são filhas dele e começam com o mesmo prefixo, mas o seletor olha o **root**. Apagar a classe do root (por parecer não usada, já que nenhuma regra a cita) **tiraria o restyle do X** |

## Corpo — nome, contagem e as duas opções

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.DELPLANS-NAME` | Modelo: {nome} | texto | `mustache:41-44` — str em `:42`, valor em `:43` | str `managetemplates_delete_template` = "Modelo:" · `{{name}}` | o nome vem do `data-name` do gatilho (`js:248`), **escapado** pelo Mustache (`{{name}}`, não `{{{name}}}`). O `<strong>` é `.local-dimensions-delete-template-shortname` (`styles.css:5420-5424`: `#1c2433`, 1.05rem/600). É `shortname`, não `name` — o contexto do template documenta "Template short name" (`mustache:28`) |
| `MOD.DELPLANS-INPLANS` | Este modelo está em **N planos** de alunos. | texto | `mustache:45-47` | str `managetemplates_delete_inplans` com `{{plancount}}` | **o `<strong>` está dentro da própria string** (`lang/pt_br:445` = `'Este modelo está em <strong>{$a} planos</strong> de alunos.'`), não no template — o `{{#str}}` o entrega como HTML. **Plural não é tratado**: com um plano, lê-se "1 planos" (vale para as duas notas também, `:444` e `:449`) |
| `MOD.DELPLANS-LEGEND` | O que fazer com os planos de aprendizagem? | legend (sr-only) | `mustache:49` | str `managetemplates_delete_options` · `.sr-only.visually-hidden` | **invisível**; existe só para o leitor de tela nomear o `<fieldset>` (`:48`). Leva **as duas** classes — `sr-only` (BS4, Moodle 4.5) e `visually-hidden` (BS5) — porque as *classes* são bridgeadas no 4.5 (ao contrário dos `data-` attributes, que não são). É o único texto do modal que o usuário vidente não lê, e o único lugar onde a pergunta do mapa antigo ("O que fazer com eles?") sobreviveu |
| `MOD.DELPLANS-UNLINK` | Desvincular | rádio (**padrão**) | `mustache:50-60` — rádio `:51`, título `:54`, nota `:57` | `value="unlink"` · `checked` | strs `managetemplates_delete_unlink` + `managetemplates_delete_unlink_note` ("Os {$a} planos continuam existindo, sem modelo."). Nasce marcado: **o estado padrão é o não destrutivo**, e o `!!checked &&` de `js:260` garante que mesmo sem nada marcado o resultado seria `false` (desvincular). O `<label>` embrulha o input — **sem `for`**, a linha inteira é alvo de clique |
| `MOD.DELPLANS-DELETE` | Excluir os planos | rádio (destrutivo) | `mustache:61-71` — rádio `:62`, título `:64-66`, nota `:68` | `value="delete"` · `.text-danger` no título | strs `managetemplates_delete_deleteplans` + `managetemplates_delete_deleteplans_note` ("Remove os {$a} planos dos alunos — irreversível."). O `.text-danger` (`:64`) é **o único sinal de perigo por cor**, e é cor de **texto**: a caixa marcada fica azul como a segura. Ver "Contraste" e "to-be" |

## O confirmar — fecha antes de escrever

O handler (`js:257-261`) escuta `ModalEvents.delete` — que é `'modal-delete-cancel:delete'`
(`lib/amd/src/modal_events.js:33`, com o comentário do core: *"Delete is a reserved word"*, e por
isso a chave está entre aspas no objeto). Ele lê o rádio marcado com
`querySelector('input[name="local-dimensions-delete-template-choice"]:checked')` e chama
`remove(!!checked && checked.value === 'delete')` (`:260`). **O contrato inteiro entre o Mustache e o
AMD é o par `name`/`value`**, escrito por extenso nos dois lados (`mustache:51`, `:62`; `js:259-260`)
— não há `data-` attribute, classe ou id intermediando.

**O modal fecha antes de a escrita voltar.** O `registerCloseOnDelete` do core
(`lib/amd/src/modal.js:1124-1139`, ligado pelo `modal_delete_cancel.js:56`) dispara o evento e,
**se ninguém chamou `preventDefault()`**, destrói o diálogo (`removeOnClose: true` → `destroy()`).
O `deleteTemplate` **não** chama `preventDefault`: um `grep -n 'preventDefault'
amd/src/central/plans.js` devolve duas linhas (`:479`, `:493`), e as duas são do drag-and-drop da
árvore, não deste modal. Consequência lida direto da cadeia: o `core_competency_delete_template`
(`js:241-244`) resolve com o diálogo **já fora da tela** — erro vira **toast de página**
(`notifyError`), não erro no diálogo; sucesso aparece como o pane recarregado (`reloadPane`, `:244`).

É a **mesma mecânica** do `MOD.BROWSER`, e aqui, como lá, está **certa**: é confirmação de uma
tacada, sem estado para preservar. A diferença é que lá isso deixa uma ponta solta (o save sem
seleção fecha calado, porque o `return` do handler não impede o fechar do core); **aqui não há ponta
solta**, porque um rádio está sempre marcado e não existe "escolha vazia" — o `!!checked` é cinto de
segurança para um caso que o `checked` do `mustache:51` já impede.

## O caminho **sem** planos (o fallback do core)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.DELPLANS-FALLBACK` | Excluir · "Excluir o modelo de plano de aprendizagem '{nome}'?" | diálogo do core | `js:265-271` | `Notification.deleteCancelPromise` | **título** = str core `delete` ("Excluir"); **corpo** = str `deletetemplate` do **`tool_lp`** (`admin/tool/lp/lang/en/tool_lp.php:92` = `Delete learning plan template '{$a}'?`) com o nome. A ordem dos args é fácil de ler errado: a assinatura é `deleteCancelPromise(title, question, deleteLabel, …)` (`lib/amd/src/notification.js:325`), e o `js:267` passa `getString('delete')` como **título** e a str do `tool_lp` como **pergunta** — o `deleteLabel` fica `undefined`, então o botão usa o rótulo padrão do core. Cancelar **rejeita** a promessa → `catch` → `return` (`:268-270`), sem chamar nada. Confirmar chama `remove(false)` (`:271`): **sempre desvincular** — argumento inócuo, já que não há plano para desvincular |

**A única dependência do `tool_lp` que sobrou.** O modal novo é 100% strings do plugin; o fallback
ainda pede uma string do `tool_lp` (`js:265`) — um `grep -rn "deletetemplate'" amd/src/` devolve
**essa única linha**. É o resto da era pré-`820a449`, quando os dois lados vinham de lá
(`deletetemplatewithplans`, `unlinkplanstemplate`, `deleteplans`, os três hoje sem uso no plugin).

**Nota de cobertura — o Behat testa o caminho que o mapa antigo nem mencionava, e só ele.** O
`manage_plans.feature:33-42` é o cenário *"Delete a template that has no plans"*: cria um template
`Disposable` **sem planos**, clica em "Delete template" (`:40`) e depois em
`I click on "Delete" "button" in the "Delete" "dialogue"` (`:41`). Esse `"Delete"` como nome do
**dialogue** casa o **título** do `deleteCancelPromise` — a str core `delete` —, o que confirma a
regra de Behat da casa: o diálogo casa **pelo título**, não pela palavra "Confirmação". Ou seja, a
observação do mapa antigo estava **certa**, mas colada no diálogo errado.

**O caminho com planos não tem nenhum teste**: nem Behat (o cenário existente escolhe de propósito um
template sem planos, que é o que faz o gate desviar) nem PHPUnit (o que o `js:260` decide é
client-side, e o `core_competency_delete_template` que ele chama é do core). Os rádios, o `value` e a
conversão para booleano são, hoje, verificados só por leitura.

## Contraste — medido nos literais shipados

O bloco `styles.css:5406-5478` usa **literais**, sem variante dark, por decisão registrada no próprio
comentário (`:5410-5414`): o corpo é renderizado no nível do `<body>`, fora do
`.local-dimensions-manage`, então as custom properties do hub não estão no escopo. Medido no DOM
(fórmula WCAG 2.x; animações canceladas antes de ler, senão a leitura volta o tema anterior):

| Par | Onde | Razão | Veredito |
| --- | --- | --- | --- |
| `#1c2433` sobre branco | nome do template (`:5420-5424`) | **15,56:1** | passa |
| `#3a4658` sobre branco | "está em N planos" (`:5426-5430`) | **9,56:1** | passa |
| `#6c7787` sobre **branco** | nota da opção **não** marcada (`:5475-5478`) | **4,54:1** | passa por 0,04 |
| `#6c7787` sobre **`#e6f0fb`** | nota da opção **marcada** (`:5477` sobre `:5454`) | **3,94:1** | **reprova** o 4,5:1 |
| `#cdc3b0` sobre branco | borda da opção não marcada (`:5447`) | **1,75:1** | falha 3:1 (não-texto) |
| `#cee0f3` sobre branco | borda da opção **marcada** (`:5453`) | **1,35:1** | falha 3:1 — **menor** que a da não marcada |

Dois achados que só aparecem quando se mede o **estado marcado**, não o de repouso:

1. **A nota reprova exatamente no estado padrão.** `#6c7787` passa sobre o branco (4,54:1) e reprova
   sobre o `#e6f0fb` que o próprio marcado pinta (3,94:1). Como o `MOD.DELPLANS-UNLINK` nasce
   `checked` (`mustache:51`), **é o estado com que o modal abre** — não é caso de borda. É a
   armadilha da Task 12 ao contrário: o rótulo passa no repouso e reprova no estado real.
2. **Marcar deixa a caixa menos visível.** A borda marcada (`#cee0f3`, 1,35:1) é mais **clara** que a
   não marcada (`#cdc3b0`, 1,75:1). O reforço visual anda para trás. **Não é reprovação de
   controle** — quem carrega o estado de fato é o `<input type="radio">` nativo, que o CSS só toca no
   `margin-top` (`:5462-5464`) —, mas o tint que deveria reforçar enfraquece.

As bordas fracas são o mesmo caso conhecido do kit (`--border-strong`/`--border-stronger` reprovam
3:1 em todas as superfícies recentes) e **não** são consertadas aqui.

## to-be — o estado marcado acompanha a consequência

**O achado, lido no CSS e dirigido no preview.** O `styles.css:5452-5455` é **uma** regra —
`.local-dimensions-delete-template-option:has(input:checked)` — e vale para as **duas** opções.
Medido no DOM depois de clicar o rádio destrutivo: `background: rgb(230, 240, 251)` e
`border-color: rgb(206, 224, 243)` — **exatamente** o `#e6f0fb` / `#cee0f3` que "Desvincular" recebe.
A escolha **irreversível** é confirmada no **mesmo azul** da escolha segura, num modal cujo botão de
confirmar (`MOD.DELPLANS-CONFIRM`) já é vermelho nas duas. O corpo diz a consequência **em prosa**; a
**cor** não a acompanha.

**A correção são três linhas, sem JS.** Um segundo seletor
`.local-dimensions-delete-template-option:has(input[value="delete"]:checked)` com o par de perigo,
depois da regra atual. O `value="delete"` **já está** no markup (`mustache:62`) e **já é** o que o JS
lê (`js:260`) — não entra atributo novo, classe nova, nem contrato novo entre Mustache e AMD; e o
`:has()` já é a mecânica da regra existente, então não entra dependência nova.

**O vermelho precisa de par próprio — medido.** O par de perigo do Moodle **não** serve para texto
sobre o próprio preenchimento: `--text-danger` `#ca3120` sobre `--bg-danger` `#f4d6d2` dá **3,88:1**
(título) e **3,54:1** (nota) — reprova. A tela usa **`#8a1e12`** (**6,77:1**). No escuro o par do tema
já passaria (`#df8379` sobre `#51140d` = **5,26:1**); a tela usa `#e89b93` (**6,55:1**) para manter a
mesma margem. O preenchimento vermelho **sozinho** é fraco contra a superfície (**1,36:1** claro,
**1,12:1** escuro) — quem carrega a caixa é a **borda** (`#51140d`, **14,43:1**), como no azul do
as-is. Mesmo precedente de par próprio medido do `--info-fg` do `mod-browser`.

**O que o to-be não conserta:** a nota a 3,94:1 do estado padrão (achado 1 acima) é do par
`#6c7787`/`#e6f0fb`, que é o caminho **azul** — sobrevive à regra nova. E a inversão das bordas
(achado 2) é do azul também. Os dois pedem mexer nos literais do `:5447`/`:5453`/`:5477`, que é outra
mudança.

## Resumo das divergências as-is ↔ mapa/tela antigos

| O que o mapa/tela antigos diziam | O que está no ar |
| --- | --- |
| To-be "consequência explícita" **não construído** | **shipado** no `820a449` (2026-07-01): nome + contagem real + nota por opção |
| `templates/central/delete_template_plans.mustache` | `templates/delete_template_modal.mustache` — **na raiz**, o único modal do kit fora de `central/` |
| Corpo = str `deletetemplatewithplans` do `tool_lp` + 2 rádios crus `value="0"`/`"1"` | strings **próprias** (`managetemplates_delete_*`), `value="unlink"`/`"delete"`, nota de consequência por opção; `deletetemplatewithplans` **sem uso** no plugin |
| Um diálogo só | **dois**: gate `has_related_data` (`js:236-239`) → modal do plugin **ou** `deleteCancelPromise` do core (`js:265-271`) |
| "O valor (0/1) vira o argumento `deleteplans`" | o valor é `unlink`/`delete`; o **JS** converte para booleano (`js:260`) |
| Nota de Behat colada no modal | vale para o **fallback** (`manage_plans.feature:33-42`); o modal **não tem cobertura** |
| To-be "parte do `modal-shell.html` (confirmação saveCancel)" | é **`ModalDeleteCancel`** (`js:251`), não `saveCancel`; e um `grep -n 'DELPLANS\|delete_template' modal-shell.html` no `modal-shell.html` devolve **nada** — a tela nunca esteve lá |
| `DELPLANS-MSG` / `-UNLINK` / `-DELETE` (3 controles, sem prefixo) | `MOD.DELPLANS-*` (12 controles) + `PLN-DELETE` reusado; `MSG` aposentado, virou `-NAME` + `-INPLANS` |
