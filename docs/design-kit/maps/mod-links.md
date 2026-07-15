# Mapa de Campos — `MOD.LINKS` · Modal vínculos curso↔atividade (as-is)

Modal aberto pelo botão **🔗 Cursos e atividades** do **sticky-footer** da aba Estrutura. Gerencia os
vínculos de uma competência em **dois níveis** — curso e atividade —, cada um com o seu **outcome**
próprio, que salva **na hora** (não há botão de salvar). Cada curso vinculado é um **card** com
contagem e distintivo de regra de conclusão; as atividades moram **dentro** da borda do card e
carregam **preguiçosamente** na primeira expansão.

A casca é Mustache; **todo o resto é construído em JS**. O Mustache tem **55 linhas** e entrega 6
controles — o `competency_links.js` tem **889** e entrega o resto.

- **Mustache:** [`competency_links.mustache`](../../../templates/central/competency_links.mustache) (55, só a casca) · gatilho em [`structure_footer_actions.mustache`](../../../templates/central/structure_footer_actions.mustache) (`:53-56`)
- **AMD:** [`competency_links.js`](../../../amd/src/central/competency_links.js) (889) · [`course_datasource.js`](../../../amd/src/central/course_datasource.js) (83, datasource do autocomplete) · usa `errors.js` (`notifyError`)
- **WS:** 9 funções, todas conferidas em `db/services.php` — ver a tabela no fim
- **CSS:** [`styles.css:5488-5644`](../../../styles.css) (card, linha de atividade, distintivo, busca de atividade) · [`styles.css:5655-5668`](../../../styles.css) (o chevron do autocomplete)
- **Tela no DS:** [`screens/mod-links.html`](../screens/mod-links.html) (as-is ↔ to-be, com a expansão dirigida e medida)

> **Resync 2026-07-15 — as 6 refs do mapa antigo estão TODAS certas, e é o primeiro mapa da série
> onde isso acontece. O problema é outro: o mapa cobria 6 dos ~26 controles, e a tela desenhava
> linhas que não existem mais.** Medido, não estimado:
>
> - **6 refs; 6 corretas (6/6).** Um `grep -oE '[a-z_/.]+\.(php|js|mustache|css):[0-9]+(-[0-9]+)?'`
>   no arquivo antigo devolve **exatamente 6**, todas em `competency_links.mustache` — e todas
>   resolvem para o elemento certo. O motivo é mecânico e vale registrar: o Mustache **não é tocado
>   desde `bcdbea1` (2026-06-28)**, o commit que criou o modal. Ref que aponta para arquivo parado
>   não apodrece.
> - **Zero refs de JS — de novo, e aqui dói mais que nos irmãos.** O próprio mapa antigo admitia:
>   *"Injetado via JS (detalhar ao inventariar `competency_links.js`)"*, e listava dois IDs
>   hipotéticos (`MOD.LINKS-COURSEROW-*`, `MOD.LINKS-ACTIVITYROW-*`). Nenhum dos dois existe neste
>   mapa: as linhas reais não são "linha de curso" e "linha de atividade", são um **card** com
>   cabeçalho, linha de outcome, nota de curso-inteiro e um contêiner preguiçoso. **6 IDs mapeados
>   contra ~20 construídos em JS.**
> - **A tela envelheceu por seis reworks, não cinco.** O `screens/mod-links.html` nasceu em
>   `159a800` (**2026-06-29**); um `git log 159a800..HEAD -- amd/src/central/competency_links.js`
>   mostra hoje **nove** commits depois disso — `fb8c725`, `93e4f69`, `d7578b3`, `7902bd8`,
>   `c10acd0`, `e0fe81d` e, desde **2026-07-15**, `5d9da31`, `cf6dad6` e `59af0f4`. **Seis** são os
>   reworks que envelheceram a tela; os **três** de 2026-07-15 são correções e não redesenham nada —
>   `5d9da31` (estado vazio) e `cf6dad6` (aritmética do cursor) mexem no `MOD.LINKS-EMPTY`, e
>   `59af0f4` (foco após remoção) não tem pixel: a tela não mostra foco de teclado. Por isso a
>   tabela do fim segue sendo a dos seis. O `fb8c725` (contagem de cursos atualizada no lugar) é
>   o que costuma escapar da lista.
> - **A tela desenhava checkbox de atividade — e checkbox não existe mais.** O `93e4f69` de fato
>   trouxe checkboxes (`box.type = 'checkbox'`, duas vezes); o `d7578b3`, **no mesmo dia**, os
>   removeu e pôs no lugar a **busca de atividade** + botão **✕** por linha. Um
>   `grep -nE 'checkbox|form-check'` no JS de hoje devolve **nada**. Os dois painéis da tela antiga
>   — as-is **e** to-be — desenhavam `☑`/`☐`.
> - **O texto do aviso de estrutura oculta era inventado, e dizia a regra errada.** A tela escrevia
>   *"Framework oculto — vínculos não aparecem para alunos"*. A string real
>   (`central_links_hiddenframework`) é *"Esta competência pertence a uma estrutura oculta e **não
>   pode ser vinculada** a cursos."* — e a consequência mecânica que faltava nos dois arquivos é que
>   o **picker é desabilitado** (`js:452`). Os vínculos existentes continuam listados e editáveis.
> - **O contador "2 / 5 atividades" não existe.** A string é `central_links_modulecount` = *"{$a}
>   atividades"* — só a contagem de **vinculadas**, sem denominador. O total do curso nunca é
>   mostrado.
> - **Faltava inteiro:** o **outcome de dois níveis** (o eixo do modal), o distintivo de **regra de
>   conclusão**, o **aviso de competência compartilhada**, a **busca de atividade** com dobra de
>   acento, a **carga preguiçosa**, o **toast hospedado no modal** (este arquivo é o caso de
>   referência) e o **recálculo da contagem no fechamento**.

> **Adendo do resync de 2026-07-15 (tarde) — e ele corrige o "6/6" acima.** Aquele placar mediu só a
> família de refs que o mapa **antigo** carregava: Mustache. O mapa **novo** trouxe refs de
> `styles.css` — **12 ocorrências, 10 faixas distintas** — e **todas as 12 estavam erradas em −4** no
> momento em que a auditoria se declarou conferida. A causa não é este modal: o `84a930d` apagou o
> bloco `.local-dimensions-central-fwcard.is-hidden { opacity: 0.6; }` (4 linhas, `~:3697`) lá em
> cima no arquivo, e **tudo abaixo subiu 4**. E o `84a930d` é **ancestral** do `08b6c51`
> (`git merge-base --is-ancestor 84a930d 08b6c51` → verdadeiro), então o CSS já estava na forma de
> hoje quando o mapa foi sincronizado: as refs foram **herdadas, não re-derivadas**. É a lição do
> `fd8666f` cobrando juros — uma auditoria que grepa só a família de refs que espera encontrar mede a
> própria expectativa, e um placar de `6/6` sobre a amostra errada é pior que placar nenhum, porque
> compra confiança. As 12 foram re-derivadas contra a fronteira real de cada bloco (abre no seletor,
> fecha na chave), não por aritmética.

## Gatilho (na aba Estrutura, fora do modal)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.LINKS-ACTION` | Cursos e atividades | botão (gatilho) | `structure_footer_actions.mustache:53-56` | `data-action="links"` · `fa fa-link` | str `central_links_button`. Mora no **sticky-footer** compartilhado da aba, não numa linha. `structure.js:1262-1271` chama `openLinksModal({competencyid, competencyname, courseoutcomes, moduleoutcomes, onClose})` (import em `:37`) com as `data-*` da linha ativa. Os dois vetores de outcome vêm do **servidor**, não do JS |
| `MOD.LINKS-COUNT-REFRESH` | `[sem rótulo]` | efeito de fechamento | `competency_links.js:880-887` → `structure.js:908-920` | `onClose(count)` | é o `fb8c725`, e a claim **procede**. O modal conta `state.rowsEl.children.length` (`js:884` — cada filho do contêiner é **um** card) e devolve; `updateCourseCount` grava em `row.dataset.courses` (`:912`), repinta o detalhe só se a linha ainda for a ativa (`:913`) e **pisca** a linha (`:919`). **Sem reload do pane** — seleção e expansão da árvore sobrevivem. `count` pode ser `null` (modal fechado antes do `shown`), e o handler sai calado (`:909-911`) |

## Casca do modal (Mustache — as 6 refs que já estavam certas)

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.LINKS-TITLE` | Cursos e atividades — {nome} | título | `competency_links.js:782` (str), `:812` (`Modal.create`) | str `central_links_title`, `$a` = nome | `core/modal` **puro**, sem `footer` no config — o 7º do censo do IMP-06. **`large: true`** (`:812`) é o único ajuste de largura que este modal tem hoje. `setRemoveOnClose(true)` em `:813` |
| `MOD.LINKS-ROOT` | `[sem rótulo]` | região/raiz | `competency_links.mustache:32` | `data-region="competency-links"` · `.local-dimensions-central-links` | os dois listeners delegados (click e change) pousam aqui (`js:864-876`), **não** no root do modal |
| `MOD.LINKS-HIDDENFW` | Esta competência pertence a uma estrutura oculta e não pode ser vinculada a cursos. | alerta | `competency_links.mustache:33-35` | `data-region="hiddenframework"` · `role="status"` · nasce `hidden` | str `central_links_hiddenframework`. **Não é uma nota decorativa:** `js:451-452` liga o alerta **e desabilita o picker** no mesmo par de linhas (`hiddenframeworkEl.hidden = response.canlink` / `addsel.disabled = !response.canlink`). O `canlink` do WS é literalmente a visibilidade da estrutura — `get_competency_links.php:91`: `(bool) $competency->get_framework()->get('visible')`. Os vínculos **existentes** continuam listados, com outcome editável: o bloqueio é só para **novos** |
| `MOD.LINKS-ADD-LABEL` | Adicionar curso | rótulo | `competency_links.mustache:37-39` | str `central_links_addcourse` · `for="local-dimensions-links-add"` | é um `<label>` de verdade, com `for` — ao contrário do `MOD.RELATED-ADDLABEL`, que mira numa árvore e por isso é um `<div>` |
| `MOD.LINKS-ADD` | Vincular curso — busque por nome, nome breve ou número de ID… | autocomplete | `competency_links.mustache:40-44` | `data-region="course-add"` · `data-competencyid` · `data-exclude` · `.form-select` | str `central_links_addcourse_placeholder`. Ver a seção do picker abaixo |
| `MOD.LINKS-ROWS` | `[sem rótulo]` | contêiner-JS | `competency_links.mustache:46` | `data-region="course-rows"` | **um filho = um card de curso**, e é isso que o `MOD.LINKS-COUNT-REFRESH` conta |
| `MOD.LINKS-EMPTY` | Nenhum curso vinculado. | estado vazio | `competency_links.mustache:47-49` | `data-region="course-empty"` · nasce `hidden` | str `central_links_nocourses`. A condição é mais estreita do que "a lista zerou": `js:464` só o mostra com `state.offset === 0 && response.total === 0`. Some no `onAddCourse` (`:713`). **CORRIGIDO em 2026-07-15** (`5d9da31`). São **três** as transições para o vazio e só **duas** estavam ligadas: `removeCourse` (`js:572-609`) tirava o card e **não** mexia no vazio, então desvincular o **último** curso deixava o painel em branco, calado, em vez da mensagem. Agora fecha em `js:603`. **O predicado não é o do irmão** — o `removeRelated` do `related_competencies.js` (`:169-194`) resolve isso com `children.length > 0` (`:187`) e ali está certo, porque **aquela lista de relações não é paginada** (refaz tudo do servidor a cada leitura, `related_competencies.js:131`, então as linhas renderizadas são a verdade inteira; o `loadmore` daquele arquivo é da **árvore de estruturas**, não desta lista). Esta pagina de 25: contêiner vazio só significa "nenhum curso vinculado" quando **não sobrou nada a carregar**. Com 30 vínculos, o one-liner ingênuo imprimiria "Nenhum curso vinculado." **acima de um "Carregar mais" vivo com 5 cursos ainda ligados** — trocaria painel branco por afirmação falsa, que é o pior dos dois. Por isso o `\|\| !state.loadMoreEl.hidden`: o módulo já rastreava essa verdade no próprio botão. Some da tela sem recarregar. **O defeito de paginação que esta linha registrava como "ainda em aberto" foi corrigido no mesmo dia — e o que sobrou dele mudou de lugar: ver "A aritmética do cursor", abaixo** |
| `MOD.LINKS-LOADMORE` | Carregar mais | botão | `competency_links.mustache:50-53` | `data-action="loadmore"` · wrap `data-region="loadmore-wrap"` nasce `hidden` | str `central_links_loadmore`. Página de **25** (`PAGE_SIZE`, `js:42`). `js:465`: `loadMoreEl.hidden = state.offset >= response.total`. **Botão, não sentinela** — ao contrário da árvore do `MOD.RELATED`, aqui não há scroll infinito. Enquanto visível, é também o alvo **preferencial** do `restoreFocus` (`js:556-558`) — ver "A aritmética do cursor" |
| `MOD.LINKS-TOAST` | `[sem rótulo]` | feedback | `competency_links.js:857` (região), `:854` (`ModalEvents.shown`) | `addToastRegion(modal.getBody()[0])` | **5** `addToast` próprios: `courseremoved` (`:608`), `activityadded` (`:631`), `activityremoved` (`:666`), `courseadded` (`:714`), `saved` (`:870`) — mais o de rede do `notifyError`. Ver a seção do toast abaixo: este arquivo é o caso de referência do padrão |

## O picker de adicionar curso

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.LINKS-ADD-ENH` | `[sem rótulo]` | fiação | `competency_links.js:767-772` (`bindPicker`) | `enhance(selector, false, DATASOURCE, placeholder)` | `false` = **single-select**. Chamado no `shown` (`:877`) — o `enhance` do core resolve por `document.querySelector`, então antes do `modal.show()` não acharia nada. **O `enhance` esconde o `<select>` original** — é por isso que o `restoreFocus` mira o input enhanced (`SELECTORS.addInput`, `js:51`) e nunca o `state.addsel` |
| `MOD.LINKS-ADD-RESET` | `[sem rótulo]` | re-render | `competency_links.js:715-717` | `Templates.replaceNodeContents(addsel.parentElement, state.addshtml, '')` | um single-select do `core/form-autocomplete` **não tem API de limpar**: a casa re-renderiza o `<div class="mb-3">` inteiro a partir do HTML guardado no `shown` (`:863`) e re-faz o `bindPicker`. É por isso que o `state.addsel` é **re-buscado** em `:768` a cada bind |
| `MOD.LINKS-ADD-EXCL` | `[sem rótulo]` | exclusão | `course_datasource.js:70-72` | `data-exclude` (CSV de ids) | lido via `element.dataset` **a cada busca** (`processResults`), nunca por jQuery `.data()` — que cachearia. O `state.excluded` é um `Set` mantido nos três pontos que mexem na lista: `loadCourses` (`js:459`), `onAddCourse` (`:712`) e `removeCourse` (`:588`) |
| `MOD.LINKS-ADD-SUG` | {nome} `{nome breve}` | sugestão | `course_datasource.js:75-82` | `.local-dimensions-central-links-code` | o nome breve entra **monoespaçado** dentro do label; `escapeHtml` (`:55-59`) escapa os dois lados, porque o label do autocomplete é HTML. `styles.css:5620-5629` dá cor própria ao código **e** um `:hover`/`[aria-selected]` para ele não sumir na sugestão destacada |
| `MOD.LINKS-ADD-CHEVRON` | `[sem rótulo]` | afinação visual | `styles.css:5655-5668` | `.local-dimensions-central-page .form-autocomplete-downarrow` | é o `c10acd0`, e a claim **procede** — mas o **escopo** é o detalhe que decide se ela vale aqui. O seletor parece um escopo de página (o modal do core **não** nasce dentro do `<div>` da página: `modal.js:133` faz `document.body.append(this.attachmentPoint)`), só que `local-dimensions-central-page` é uma **classe de body** (`central.php:57`, `$PAGE->add_body_class`). Como o modal é filho do `body`, o descendente casa e **o chevron chega aqui**. `font-size:0` mata o glifo do core; o `::before` põe o `\f107` do Font Awesome, igualando o chevron do `.form-select` |

## O card de curso (construído em JS)

`makeCourseRow` (`competency_links.js:352-437`) monta **um** card: cabeçalho + linha de outcome +
nota de curso-inteiro + contêiner de atividades, tudo dentro de **uma** borda
(`.local-dimensions-central-links-card`, `styles.css:5488-5496`).

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.LINKS-CARD` | `[sem rótulo]` | card | `competency_links.js:352-437` | `data-courseid` · `data-fullname` · `data-paged` | o `data-fullname` existe só para o confirm de remoção ler o nome sem re-consultar o DOM (`js:574`). O `data-paged` é da paginação e **não** nasce aqui: quem o põe é o `loadCourses` (`:457`), porque o picker também chama o `makeCourseRow` — ver "A aritmética do cursor" |
| `MOD.LINKS-CARD-TOGGLE` | `[sem rótulo]` | botão (expandir) | `competency_links.js:361-369` | `data-action="toggle-course"` · `aria-expanded` · `fa fa-chevron-right` | **botão sem rótulo textual e sem `aria-label`** — o nome acessível vem só do `aria-expanded`. É o único controle do modal nessa condição (os outros três ícones têm `.sr-only`); registrado, não corrigido aqui. `toggleCourse` (`js:522-537`) troca o ícone para `fa-chevron-down` e carrega na **primeira** abertura. **É também o destino de foco preferido das duas remoções** (`:607`, `:665`) — escolhido justamente por ser o único controle da linha **sempre** renderizado: o lixo é gated por capability |
| `MOD.LINKS-CARD-NAME` | {nome do curso} | link | `competency_links.js:375-388` | `target="_blank"` · `rel="noopener noreferrer"` | vira `<span>` quando o WS não manda `courseurl` (o usuário não pode ver o curso). Sendo link, ganha `fa-external-link` + `.sr-only` "abre em nova janela" (`decorateExternalLink`, `js:134-143`, str **`opensinnewwindow` do core** — sem string nova) |
| `MOD.LINKS-CARD-SHORT` | {nome breve} | texto | `competency_links.js:390-392` | `.font-monospace small text-muted` | |
| `MOD.LINKS-CARD-COUNT` | 1 atividade / {n} atividades / Curso inteiro | contador | `competency_links.js:394-396` (nó), `:197-210` (`updateCourseMeta`) | `data-role="modcount"` | strs `central_links_modulecountone` / `central_links_modulecount` / `central_links_wholecourse`. **Três estados, não dois:** com 0 vinculadas o contador **vira o rótulo "Curso inteiro"** e a nota aparece. O `{count}` é substituído em JS (`:204`) sobre um template pedido **uma vez** no lote — é o outro lado do `c10acd0` |
| `MOD.LINKS-CARD-REMOVE` | Remover curso | botão | `competency_links.js:403-405` (`iconButton`, `:98-113`) | `data-action="remove-course"` · `fa fa-trash` | `title` + `.sr-only`. `removeCourse` (`js:572-609`): confirm `deleteCancelPromise` → WS → tira do `excluded` → remove o card → **devolve o cursor** (`:597-600`) → **religa o vazio** (`:603`) → **restaura o foco** (`:607`) → **toast** (`:608`, é o `e0fe81d`). Os três últimos passos são de 2026-07-15 (`5d9da31`, `cf6dad6`, `59af0f4`). Só sai quando `course.canmanage`. **O botão continua vivo através dos dois `await`** (confirm e unlink) — é essa a corrida que obriga o cursor a ser idempotente |
| `MOD.LINKS-CARD-OUTCOME` | Outcome: | select | `competency_links.js:407-417` | `data-role="course-outcome"` · `name="course-outcome"` | str `central_links_outcomeprefix`. As opções vêm de `state.courseoutcomes`, passado pelo **servidor** via `opts`. Salva no `change` (`js:865-876`) → `saveOutcome` (`:676-690`) → toast "Salvo" + flash. `disabled` quando `!canmanage` |
| `MOD.LINKS-CARD-BADGE` | Possui regra de conclusão / Criar regra de conclusão | distintivo | `competency_links.js:417` → `makeCompletionBadge` (`:154-172`) | `.local-dimensions-central-links-badge-ok` / `-warn` | strs `central_links_completionrule_ok` / `_missing`. Verde com `fa-check`, âmbar com `fa-exclamation-triangle`. Vira **`<a>`** (para `course/completion.php`) só quando o WS manda `completionurl`; sem url é um `<span>` — o mesmo nó, duas tags (`:156`). `styles.css:5509-5545` |
| `MOD.LINKS-WHOLENOTE` | Vínculo no nível do curso, sem atividade específica. | nota | `competency_links.js:419-423` | `data-role="wholecoursenote"` · nasce `hidden` | str `central_links_wholecoursenote`. Alternada **só** por `updateCourseMeta` (`:202`, `:205`, `:208`), sempre em contraponto ao contador |
| `MOD.LINKS-ACTS` | `[sem rótulo]` | contêiner preguiçoso | `competency_links.js:425-429` | `data-role="activities"` · `data-loaded="0"` · nasce `hidden` | `.local-dimensions-central-links-acts` (`styles.css:5503-5507`) — a indentação e a borda que põem as atividades **dentro** do card. O `data-loaded` é a memória da carga: `toggleCourse` só chama `loadActivities` quando `!== '1'` (`js:531`), e `addModule`/`removeModule` o zeram (`:628`, `:659`) para forçar a releitura |

## As atividades (dentro do card, carga preguiçosa)

`loadActivities` (`competency_links.js:476-512`) faz **uma** leitura que devolve `linked` +
`available` juntos, e reconstrói o contêiner inteiro. **Recalcula a contagem do card a partir do
dado fresco** (`:511`) — é o ponto onde uma contagem do servidor desatualizada se corrige sozinha.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.LINKS-ACTS-HDR` | Atividades vinculadas · outcome ao concluir a atividade | cabeçalho | `competency_links.js:493-496` | str `central_links_activitieshdr` | a string **é** a explicação do eixo do modal: o outcome da atividade dispara na conclusão **dela**, não do curso |
| `MOD.LINKS-ACTSEARCH` | Adicionar atividade — buscar por nome… | campo de busca | `competency_links.js:283-342` (`makeActivitySearch`), input `:288-295` | `data-role="activity-search"` · `id` único por curso | strs `central_links_addactivity` (`aria-label`) / `_placeholder`. **Busca client-side**, sem WS: filtra o `available` que já veio na mesma leitura. É o `d7578b3`, e a claim **procede**. Só é montado com `response.canmanage && response.available.length` (`js:497`) — curso sem atividade livre não ganha campo |
| `MOD.LINKS-ACTSEARCH-FOLD` | `[sem rótulo]` | regra de casamento | `competency_links.js:62` (`fold`), usado em `:303-304` | `toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'')` | **dobra acento dos dois lados** — "prova" acha "Prova diagnóstica"; "trabalho" acha "Trabalho de Lógica". Casa por `includes`, não por prefixo. **Só o nome** entra no casamento: o tipo do módulo é exibido mas **não** é buscável (`:304`) |
| `MOD.LINKS-ACTSEARCH-LIST` | `[sem rótulo]` | dropdown | `competency_links.js:297-300`, render `:302-329` | `data-role="activity-search-list"` · nasce `hidden` | abre no `focus` **e** no `input` (`:331-332`), fecha no `Escape` (`:333-337`) e no clique fora — e o clique fora é varrido no **topo** do `onClick` delegado (`js:730-737`), antes de qualquer roteamento, para todo `activity-search` que não contenha o alvo. `styles.css:5586-5593` |
| `MOD.LINKS-ACTSEARCH-ITEM` | {nome} + {tipo} | botão | `competency_links.js:312-325` | `data-action="add-module"` · `data-cmid` | `addModule` (`js:619-632`): WS → zera `data-loaded` → recarrega → **pisca a linha nova** (`:630`, `flash`, `:179-187`) → toast. O flash mira `[data-cmid="…"]` **depois** do reload, porque o nó velho já morreu |
| `MOD.LINKS-ACTSEARCH-NONE` | Nenhuma atividade encontrada. | vazio da busca | `competency_links.js:307-310` | str `central_links_nomatches` | |
| `MOD.LINKS-ACT` | `[sem rótulo]` | linha (duas linhas) | `competency_links.js:223-272` (`makeModuleRow`) | `data-cmid` · `data-name` | `.local-dimensions-central-links-act` (`styles.css:5553-5559`). **Duas linhas** é o `d7578b3`: nome+tipo+✕ em cima, outcome+distintivo embaixo |
| `MOD.LINKS-ACT-NAME` | {nome da atividade} | texto | `competency_links.js:231-235` | `.local-dimensions-central-links-actname` · `title` = nome | **clampado no CSS** (`styles.css:5561-5570`), com o nome inteiro no `title` — é a resposta do `d7578b3` a nome de atividade longo |
| `MOD.LINKS-ACT-MTYPE` | {tipo do módulo} | etiqueta | `competency_links.js:121-126` (`mtypeTag`) | `.local-dimensions-central-links-mtype` | o nome **localizado** do módulo, vindo do WS (`styles.css:5572-5583`). Aparece na linha **e** na sugestão da busca |
| `MOD.LINKS-ACT-REMOVE` | Remover atividade | botão | `competency_links.js:239-241` (`iconButton`) | `data-action="remove-module"` · `fa fa-times` | **✕, não 🗑** — o trash é do curso, o times é da atividade: a diferença de glifo é a diferença de escopo. `removeModule` (`js:641-667`): confirm → WS → recarrega o contêiner do curso (`:657-660`) → **restaura o foco** (`:664-665`, desde 2026-07-15) → toast. O foco é consultado **depois** do reload, que esvazia e repovoa o contêiner, e fica **dentro** do card em que o usuário estava: o próximo ✕, ou o toggle do card quando a última atividade sai. Só com `module.canmanage` |
| `MOD.LINKS-ACT-OUTCOME` | `[sem rótulo]` | select | `competency_links.js:246-248` | `data-role="module-outcome"` · `name="module-outcome"` | opções de `state.moduleoutcomes` — **vetor diferente** do curso, e é por isso que o `saveOutcome` tem dois ramos (`js:678-689`) e dois WS. `aria-label` = str `central_links_outcome` |
| `MOD.LINKS-ACT-BADGE` | Possui regra de conclusão / Criar regra de conclusão | distintivo | `competency_links.js:250` | mesma `makeCompletionBadge` | aqui a url é o `editurl` (settings do módulo), não `course/completion.php` — mesmo componente, destino diferente |
| `MOD.LINKS-ACT-SHARED` | Outras {n} competências estão vinculadas a esta atividade… | alerta | `competency_links.js:253-270` | `.alert-warning` · link `central_links_opencompetencies` | strs `central_links_sharedwarning` / `_sharedwarningone` (**singular próprio**, escolhido em `js:488`). Só sai com `sharedcount > 0` **e** texto não vazio (`:253`). É o aviso de que a regra de conclusão **não é sua**: mexer nela afeta as outras competências. As N strings são pedidas **em paralelo** e **fora** do lote (`js:484-490`), porque dependem do dado da linha |
| `MOD.LINKS-ACTS-EMPTY` | Nenhuma atividade vinculada neste curso. | vazio | `competency_links.js:504-508` | str `central_links_noactivities` | é o vazio **das atividades**; não confundir com o `MOD.LINKS-EMPTY`, que é o dos cursos |

## O toast — este arquivo é o caso de referência do padrão

`competency_links.js:857` chama `addToastRegion(modal.getBody()[0])` no `ModalEvents.shown`
(`:854`), com o motivo escrito no comentário logo acima (`:855-856`). É um dos **4** módulos do
plugin com esse padrão — `participants_manager.js`, `related_competencies.js`, `frameworks.js` e
este —, contados com `grep -rln 'addToastRegion' amd/src/`. (**`-l`, não `-n`:** o `grep -rn`
devolve **8** linhas, não 4 — cada módulo casa duas vezes, no `import` e na chamada. A versão
anterior desta frase citava o `-rn` e o número dos arquivos, que não é o que aquele comando
imprime.)

O motivo é aritmética de `z-index`, e os dois números foram conferidos na fonte:

- `.toast-wrapper` da página: **`z-index: 1051`** (`theme/boost/scss/moodle/core.scss:2432`).
- `$zindex-modal`: **`1055`** (`theme/boost/scss/bootstrap/_variables.scss:1139`).

Sem região própria, um toast disparado daqui pousaria na wrapper da página e ficaria **atrás** do
diálogo. O comentário do core na linha **acima** do `z-index: 1051` (`:2431`) ainda diz que aquilo
fica *"above any modals"* — e **envelheceu**: no Bootstrap 4 o `$zindex-modal` era 1050 e a conta
fechava; o salto para o BS5 subiu o modal para 1055 e deixou a wrapper por baixo. O core remove a
região sozinho ao fechar (`removeToastRegion` no `core/modal`), então não há vazamento e **não** se
mexe em `z-index` global.

**Este modal é o que mais depende disso:** ele tem **5** `addToast` próprios — `courseremoved` (`js:608`),
`activityadded` (`:631`), `activityremoved` (`:666`), `courseadded` (`:714`) e `saved` (`:870`) —,
mais o de rede que o `notifyError` levanta. Nenhuma dessas ações fecha o diálogo, então **todas** precisam de
confirmação visível no lugar. **Três** delas também **piscam** o elemento afetado (`flash`, `js:179-187`),
que é a outra metade do par: o toast diz *o quê*, o flash diz *onde*. São `addModule` (`js:630`),
`onAddCourse` (`:711`) e `saveOutcome` (`:871`) — um `grep -n 'flash('` no módulo devolve **exatamente
essas três**, e nada mais: a declaração é `const flash = (el) =>`, que não contém a substring `flash(`.
(A versão anterior desta frase dizia "essas três **mais a declaração**", o que aquele grep nunca
imprimiu — nem hoje, nem no `08b6c51`, onde ele também devolvia 3.) As duas remoções **não** piscam,
e não teriam onde: o elemento que a ação afeta sai do DOM — é justamente por isso que elas restauram
o **foco** em vez de piscar (`59af0f4`; ver "A aritmética do cursor").

## A aritmética do cursor — e o foco que a remoção deixava cair

**CORRIGIDO em 2026-07-15** (`cf6dad6`), depois de este mapa ter registrado o defeito como "ainda em
aberto" no `MOD.LINKS-EMPTY`. **O que era:** o `state.offset` só andava para frente — é escrito num
único lugar, `loadCourses` (`js:462`, `offset += items.length`) — e o `removeCourse` não o tocava.
Desvincular uma linha já buscada encolhia a lista **debaixo** do cursor, e o "Carregar mais" seguinte
pedia um índice que não existe mais: **o curso que subiu para o lugar do removido nunca era buscado**.
Não voltava sem reabrir o modal, e nada na tela dizia que faltava um. A armadilha arma em **26**
vínculos, não em 30: 25 renderizam, o cursor para em 25, `25 >= 26` é falso, o botão fica.

**O que é:** o `removeCourse` devolve o cursor junto com a linha (`js:597-600`). Duas sutilezas que o
one-liner ingênuo erraria, e as duas estão no comentário do próprio código (`:591-596`):

- **O decremento é guardado** (`if (courseEl.dataset.paged)`) porque **`offset` não é "linhas na
  tela"**: o `onAddCourse` anexa o card do picker **sem** contá-lo (não há escrita de `offset` fora do
  `loadCourses`), então decrementar por uma linha que o cursor nunca contou o empurraria **para trás**
  e **duplicaria** um card na página seguinte. Só as linhas que o `loadCourses` buscou levam a
  etiqueta — e ela é posta **lá** (`js:457`), **não** no `makeCourseRow`, porque o picker também chama
  o `makeCourseRow`.
- **A etiqueta é consumida** (`delete courseEl.dataset.paged`) porque o passo **tem** de ser idempotente
  por linha: o botão de lixo continua vivo através dos **dois** `await` — o confirm e o unlink —, então
  um re-clique alcança a mesma linha, e o segundo unlink **resolve com `success: false` em vez de
  lançar** (`unlink_competency_course.php`: `api::remove_competency_from_course` devolve `false` e o WS
  retorna `['success' => (bool) $success]`, sem exceção). Sem o consumo, os dois passes decrementariam.

**A ordenação também fechou.** O `ORDER BY` do `get_competency_links.php` (`:127`) ganhou o desempate
por id — `c.fullname ASC, c.id ASC` —, e o porquê está no comentário logo acima (`:117-121`). Sem ordem
**total**, um cursor de offset pula ou duplica entre páginas **mesmo sem mutação nenhuma**: nome de
curso repete, e uma ordem parcial deixa o banco devolver as linhas empatadas em sequência diferente a
cada chamada. Por que `(fullname, id)` fecha: a consulta filtra **uma** competência e junta
`{competency_coursecomp} cc JOIN {course} c ON c.id = cc.courseid`, e o índice **único** de core
`courseidcompetencyid` sobre `(courseid, competencyid)`
(`lib/db/install.xml`, `UNIQUE="true"`) garante **uma** linha por curso no resultado — então `c.id`,
que já é chave primária, permanece único no conjunto e desempata sempre.

**AINDA EM ABERTO — o espelho, e ele é do mesmo tamanho.** O `onAddCourse` anexa a linha **sem contá-la**
(`js:709`), então o "Carregar mais" seguinte re-busca um índice que já está na tela e **duplica um card**.
Não se resolve com `offset += 1` no add: se o curso adicionado ordena **antes** ou **depois** do cursor
depende do `fullname` dele — só quem ordena **antes** deveria mover o cursor, e o cliente não sabe disso
sem reler. E os dois WSs irmãos seguem com o `ORDER BY` **sem** desempate: `get_competency_courses.php:87`
e `search_linkable_courses.php:119`. **A paginação não está correta — está menos errada:** a remoção
parou de pular, a adição continua podendo duplicar.

**O foco** (`59af0f4`, mesmo dia) é outro defeito das mesmas duas remoções, e entra aqui porque o alvo
que ele escolhe depende da paginação. O confirm devolve o foco ao botão que o abriu — a lixeira da
própria linha —, e as duas remoções então **destacam essa linha**: o foco caía no `<body>` e o usuário de
teclado tinha de re-atravessar o diálogo inteiro. Toda remoção por teclado batia nisso, sempre; quem usa
mouse nunca viu, e é por isso que sobreviveu. O `restoreFocus` (`js:548-563`) só age **se** o foco já
está no `<body>`, então nunca rouba foco de onde o usuário o pôs. Duas escolhas de alvo que o gêmeo do
`related_competencies` (`:152`) não podia carregar sem correção:

- **O fallback é o input *enhanced* do autocomplete** (`SELECTORS.addInput`, `js:51`,
  `[data-fieldtype="autocomplete"]`), **nunca** o `state.addsel`: o `enhance()` do core **esconde** o
  `<select>` original, que por isso não é focável — o foco ficaria calado no `<body>`.
- **O "Carregar mais" tem precedência** enquanto visível (`js:556-558`): no caminho paginado o estado
  vazio **não** aparece quando a última linha carregada sai, então o botão — e não o picker — é o
  caminho adiante.

Os alvos preferidos são os **sempre renderizados**: o toggle do card vem antes da lixeira do card
seguinte, porque a lixeira é gated por capability e o toggle não. Nenhum cenário Behat remove curso ou
atividade, então nenhum foi afetado.

## As 9 funções de web service

Todas conferidas em `db/services.php` (linha da chave), com o ponto de chamada no JS. **A coluna
"Chamado em" agora aponta sempre a linha do `methodname`** — a que nomeia o WS. A versão anterior
misturava as duas âncoras (4 no `methodname`, 4 no `Ajax.call` uma linha acima); ambas resolviam
para a mesma chamada, mas a tabela é indexada por nome de WS, então o `methodname` é a âncora útil:

| WS | `db/services.php` | Chamado em | Papel |
| --- | --- | --- | --- |
| `local_dimensions_get_competency_links` | `:229` | `js:447` | página de cursos vinculados + `total` + `canlink`. **`ORDER BY c.fullname ASC, c.id ASC`** (`get_competency_links.php:127`) — o desempate por id é de 2026-07-15 (`cf6dad6`); ver "A aritmética do cursor" |
| `local_dimensions_search_linkable_courses` | `:237` | `course_datasource.js:44` | busca do autocomplete (nome, nome breve, ID; exclui ocultos). `ORDER BY c.fullname ASC` **sem desempate** (`search_linkable_courses.php:119`) |
| `local_dimensions_link_competency_course` | `:245` | `js:704` | devolve **o curso já montado**, e é por isso que o `onAddCourse` consegue anexar o card sem reler a lista |
| `local_dimensions_unlink_competency_course` | `:253` | `js:585` | **resolve com `success: false` no duplicado, em vez de lançar** — o detalhe que obriga o decremento do cursor a ser idempotente |
| `local_dimensions_set_course_link_outcome` | `:261` | `js:681` | |
| `local_dimensions_get_competency_module_links` | `:269` | `js:480` | **uma** leitura devolve `linked` + `available` + `canmanage` |
| `local_dimensions_link_competency_module` | `:277` | `js:624` | |
| `local_dimensions_unlink_competency_module` | `:285` | `js:654` | |
| `local_dimensions_set_module_link_outcome` | `:293` | `js:687` | |

## Os seis reworks — o que procede hoje

Conferido commit a commit contra o código de hoje, não contra a mensagem:

| Commit | Claim | Procede? |
| --- | --- | --- |
| `fb8c725` | contagem de cursos atualizada no lugar, sem reload da árvore | **sim** — `js:880-887` → `structure.js:908-920` |
| `93e4f69` | card de curso | **sim** — `makeCourseRow`, `js:352` |
| `93e4f69` | **atividades com checkbox** | **NÃO** — foi revertido pelo `d7578b3` no mesmo dia. `grep -niE 'checkbox\|form-check'` no JS devolve **nada** (controle positivo: o mesmo grep em `amd/src/` acha `competency_tree_browser.js` e `enrol_methods.js`, então não é o padrão que falhou). É a claim que envelheceu a tela |
| `93e4f69` | distintivo de conclusão | **sim** — `makeCompletionBadge`, `js:154` |
| `d7578b3` | busca de atividade | **sim** — `makeActivitySearch`, `js:283` |
| `d7578b3` | linhas de duas linhas | **sim** — `makeModuleRow`, `js:229-251` |
| `d7578b3` | correção de contagem | **sim** — `updateCourseMeta` recalcula do dado fresco, `js:511` |
| `7902bd8` | erro resiliente a rede | **sim** — os **10** `.catch(notifyError)` do arquivo; `errors.js:68-80` manda falha de conectividade para toast e mantém erro de aplicação no modal do core |
| `c10acd0` | lote de strings | **sim** — **23** `getString` num `Promise.all` (`js:783-807`) e **23** `labels[…]` no `state`; o `updateCourseMeta` deixou de ser `async` |
| `c10acd0` | chevron unificado | **sim, e alcança este modal** — `styles.css:5655-5668` sob uma classe de **body** (`central.php:57`), e o modal do core é filho do `body` (`modal.js:133`) |
| `e0fe81d` | toast ao remover curso | **sim** — `js:608` |

## To-be — `MOD.LINKS-EXPAND`, expandir/restaurar (`mtube: expandir`)

Este é o **segundo** candidato do hub, depois do `mod-participants`; a mecânica desenhada é a mesma,
e o precedente é shipado.

> **Nota de nomenclatura.** O kit **não tem um `IMP-08`** — um
> `grep -rnoE 'IMP-[0-9]{2}' docs/design-kit/ | grep -v 'maps/mod-links.md'` devolve `IMP-03`,
> `IMP-05`, `IMP-06`, `IMP-10` e `IMP-11`, e nenhum deles é o expandir. (A exclusão é
> auto-referência, não conveniência: **esta frase é o único `IMP-08` do kit** — sem o filtro o grep
> acha a si mesmo, e é exatamente isso que ele devolve. Confira tirando o `grep -v`.)
> No `mod-participants`, que é a **primeira** tela a carregar
> esta melhoria, ela aparece **sem número**: só o distintivo `mtube: expandir`, sob o título
> "1 · Cabeçalho ganha ações, links descem". Esta tela segue a mesma convenção — distintivo, não
> número inventado.

Do `format_mtube`, **citado por símbolo** — o plugin **não versiona `amd/src`** (só `amd/build`),
então o fonte sobrevive apenas no `sourcesContent` dos `.map` e uma ref `arquivo:linha` de JS não
resolveria para ninguém:

- `getFullscreenButtonsHtml(expandLabel, narrowLabel)` emite **dois botões sempre presentes** —
  `.enterfullscreen` (`fa fa-expand`) e `.exitfullscreen` (`fa fa-compress`), cada um com
  `aria-label` + `title` próprios.
- `renderFullscreenButtons()` pede as strings `expand` / `narrow` e devolve o markup.
- `setModalFullscreen(root, enabled, storageKey)` faz **uma** coisa visual:
  `root.toggleClass('fullscreen', enabled)`. O `.each` acima dele só descarta o tooltip do botão que
  vai sumir — **não troca ícone**.
- Quem escolhe qual botão aparece é o **CSS**, e ele existe:
  `.modal.mtube-modal-fullscreen-capable:not(.fullscreen) .exitfullscreen` e
  `.modal.mtube-modal-fullscreen-capable.fullscreen .enterfullscreen`
  (`format_mtube/styles.css:4329-4335`) — **zero troca de ícone em JS**, confirmado.
- A largura sai de `.modal…fullscreen .modal-dialog` (`:4337`), não de estilo inline.

**A divergência é a persistência, e ela não é cosmética.** O mtube grava em `localStorage`
(`STORAGE_PREFIX = 'format_mtube.modal.fullscreen.'`), que **não sai do navegador**. O hub já
resolveu esse problema uma vez e a solução está no repositório: `amd/src/central/preferences.js`
usa `setUserPreference` do `core_user/repository` (`:28`), com **debounce de 400 ms** (`SAVE_DELAY`,
`:36`), e o docblock do módulo diz por que (`:20-21`): *"across sessions and devices — replaces the
previous per-session sessionStorage persistence"*. As duas prefs são declaradas em
`lib.php:140-151` (`local_dimensions_user_preferences()`), com `permissioncallback` =
`\core_user::is_current_user`, e os nomes moram em `constants.php:81` / `:84`.

Portanto o expandir **não precisa de pref nova**: cabe como uma chave no `PREF_CENTRAL_DISPLAY` que
já existe — o `display` já é um objeto JSON com sub-objetos por área (`preferences.js:41-48`), e
`local_dimensions_user_preferences()` já aceita o nome. **Sem WS novo, sem string de setting nova,
sem bump de `version.php`** (as prefs não são serviços).

**O que fica registrado como não resolvido:** o `large: true` de hoje (`js:812`) e a classe
`fullscreen` do to-be são **dois** mecanismos de largura; quem desenhar isso precisa decidir se o
`fullscreen` empilha sobre o `large` ou o substitui. A tela **não** demonstra isso — o preview não
roda o `core/modal`.
