s/const modal = await Modal\.create\(\{title: escapeHtml\(data\.fullname\), body: html, large: true\}\);/const modal = await Modal.create({title: data.fullname, body: html, large: true});/;
