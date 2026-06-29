# Mapa de Campos — `MOD.RULE` · Modal de regra de conclusão (as-is)

Corpo do modal nativo de configuração de regra: seletor de resultado (outcome), seletor
de tipo de regra e, para a regra por pontos, uma tabela de pontos por filha. O AMD
alterna a visibilidade do tipo/pontos e lê os valores no salvar.

- **Mustache:** [`templates/central/rule_config.mustache`](../../../templates/central/rule_config.mustache)
- **AMD:** [`amd/src/central/rule_config.js`](../../../amd/src/central/rule_config.js)
- **To-be no DS:** parcialmente em `paginated-picker.html` (controle `ruleoutcome`); tabela de pontos é candidata a novo card.

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `MOD.RULE-OUTCOME-LABEL` | Resultado | label | `rule_config.mustache:53` | str `outcome, tool_lp` | — |
| `MOD.RULE-OUTCOME` | Resultado (select) | select | `rule_config.mustache:54` | `data-region="outcome"` | valor 0 = nenhum; demais = ação ao completar |
| `MOD.RULE-TYPE-WRAP` | — | wrapper | `rule_config.mustache:60` | `data-region="ruletype-wrap"` | `hidden` se `^hasrule` |
| `MOD.RULE-TYPE-LABEL` | Tipo de regra | label | `rule_config.mustache:61` | str `central_rule_type` | — |
| `MOD.RULE-TYPE` | Tipo de regra (select) | select | `rule_config.mustache:62` | `data-region="ruletype"` | All / Points / … (classes do core) |
| `MOD.RULE-ERROR` | erro de pontos | alerta | `rule_config.mustache:68` | `data-region="error"` | `hidden` se `^showerror`; `alert-danger` |
| `MOD.RULE-POINTS` | — | wrapper | `rule_config.mustache:71` | `data-region="points"` | `hidden` se `^ispoints` |
| `MOD.RULE-POINTS-TABLE` | tabela de pontos | tabela | `rule_config.mustache:73` | — | só se `haschildren` |
| `MOD.RULE-POINTS-HEAD` | `[col 1 sem rótulo]` · Pontos · Obrigatório | cabeçalho | `rule_config.mustache:74-79` | str `points, tool_lp` / `required` | 1ª coluna (nome da filha) sem rótulo |
| `MOD.RULE-POINTS-ROW` | linha por filha | linha | `rule_config.mustache:83-87` | `data-competency="{id}"` | nome + `input[name=points]` + `checkbox[name=required]` |
| `MOD.RULE-POINTS-TOTAL` | Total necessário para concluir | linha | `rule_config.mustache:89-93` | str `totalrequiredtocomplete, tool_lp` | `input[name=requiredpoints]` (mín. 1) |
