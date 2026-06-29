# Mapa de Campos — `MOD.DELPLANS` · Excluir template com planos (as-is)

Corpo do diálogo de confirmação quando o template tem planos de aprendizagem: mensagem +
dois radios — desvincular (padrão) ou excluir os planos. O valor (0/1) vira o argumento
`deleteplans` de `core_competency_delete_template`.

- **Mustache:** [`templates/central/delete_template_plans.mustache`](../../../templates/central/delete_template_plans.mustache)
- **Acionado por:** `PLN-DELETE` em [`amd/src/central/plans.js`](../../../amd/src/central/plans.js).
- **To-be no DS:** parte do `modal-shell.html` (confirmação saveCancel).

| ID | Rótulo | Tipo | Origem | Dados | Regra / notas |
| --- | --- | --- | --- | --- | --- |
| `DELPLANS-MSG` | "Este template tem planos…" | texto | `delete_template_plans.mustache:29` | str `deletetemplatewithplans, tool_lp` | — |
| `DELPLANS-UNLINK` | Desvincular planos do template | radio | `delete_template_plans.mustache:31` | `value="0"` | **padrão** (`checked`) |
| `DELPLANS-DELETE` | Excluir os planos | radio | `delete_template_plans.mustache:37` | `value="1"` | destrutivo |

**Nota de Behat:** o diálogo casa pelo **título** (`deleteCancelPromise`), não pela palavra "Confirmação".
