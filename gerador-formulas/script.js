/**
 * script.js — versão completa com Partes → Fases → Ingredientes
 * - PESQUISAR: busca via AJAX (form #form-pesquisa) e renderiza em #tabela-formulas tbody ou #area-resultados.
 * - Ações: Abrir, Editar, Excluir (modal) e PDF (quando existir).
 * - CRIAR/EDITAR: modal com “SALVAR E SAIR” e “GERAR PDF” (abre em nova aba).
 * - CRIAR/EDITAR: cada PARTE pode ter VÁRIAS FASES; cada FASE tem ingredientes.
 * - Defensivo: só anexa eventos quando elementos existem.
 * ➜ Carregue este arquivo DEPOIS do Bootstrap JS.
 */
(() => {
  "use strict";

  // --------------------------------------------------------
  // Helpers
  // --------------------------------------------------------
  const $ = (sel, ctx) => (ctx || document).querySelector(sel);
  const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));
  const el = (tag, attrs = {}, children = []) => {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (k === "class") node.className = v;
      else if (k === "dataset") Object.assign(node.dataset, v);
      else if (k in node) node[k] = v;
      else node.setAttribute(k, v);
    }
    for (const c of children) node.appendChild(typeof c === "string" ? document.createTextNode(c) : c);
    return node;
  };
  const fmt = { text: (v) => (v == null ? "" : String(v)) };
  const toNumber = (val) => (typeof val === "string" ? Number(val.replace(",", ".").trim()) || 0 : Number(val) || 0);
  const debounce = (fn, ms = 350) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
  const safeBootstrapModal = (modalEl) => (!modalEl || !window.bootstrap ? null : (() => { try { return bootstrap.Modal.getOrCreateInstance(modalEl); } catch { return null; } })());

  // ========================================================
  // 1) PÁGINA DE PESQUISA — AJAX
  // ========================================================
  (function initBuscaAjax() {
    const form = document.getElementById("form-pesquisa");
    if (!form) return;

    const areaResultados = document.getElementById("area-resultados");
    const tbody = document.querySelector("#tabela-formulas tbody"); // se tiver tabela
    const btnPesquisar = form.querySelector("[type='submit']");

    // ---------- Renderização ----------
    const renderResultados = (lista) => {
      if (tbody) tbody.innerHTML = "";
      if (areaResultados) areaResultados.innerHTML = "";

      if (!Array.isArray(lista) || lista.length === 0) {
        const vazio = el("div", { class: "text-muted py-3" }, ["Nenhum resultado encontrado."]);
        if (tbody) tbody.appendChild(el("tr", {}, [el("td", { colspan: 8, class: "text-center" }, [vazio])]));
        else if (areaResultados) areaResultados.appendChild(vazio);
        return;
      }

      // TABELA
      if (tbody) {
        lista.forEach((r) => {
          const viewHref = r.id != null ? `view_formula.php?id=${encodeURIComponent(r.id)}` : "#";
          const editHref = r.id != null ? `editar_formula.php?id=${encodeURIComponent(r.id)}` : "#";
          const pdfHref = r.caminho_pdf ? String(r.caminho_pdf) : null;

          const tr = el("tr", {}, [
            el("td", {}, [fmt.text(r.id)]),
            el("td", {}, [fmt.text(r.nome_formula)]),
            el("td", {}, [fmt.text(r.codigo_formula)]),
            el("td", {}, [fmt.text(r.categoria)]),
            el("td", {}, [fmt.text(r.desenvolvida_para)]),
            el("td", {}, [fmt.text(r.solicitada_por)]),
            el("td", {}, [fmt.text(r.data_criacao_formatada || r.data_criacao || "")]),
            el("td", { class: "text-nowrap" }, [
              el("a", { href: viewHref, class: "btn btn-sm btn-outline-primary me-1" }, ["Abrir"]),
              el("a", { href: editHref, class: "btn btn-sm btn-outline-warning me-1" }, ["Editar"]),
              el("button", { type: "button", class: "btn btn-sm btn-outline-danger btn-open-delete me-1", dataset: { id: String(r.id ?? "") } }, ["Excluir"]),
              pdfHref
                ? el("a", { href: pdfHref, target: "_blank", class: "btn btn-sm btn-outline-secondary" }, ["PDF"])
                : el("span", { class: "small text-muted" }, ["Sem PDF"])
            ]),
          ]);
          tbody.appendChild(tr);
        });
        return;
      }

      // CARDS
      if (areaResultados) {
        lista.forEach((r) => {
          const viewHref = r.id != null ? `view_formula.php?id=${encodeURIComponent(r.id)}` : "#";
          const editHref = r.id != null ? `editar_formula.php?id=${encodeURIComponent(r.id)}` : "#";
          const pdfHref = r.caminho_pdf ? String(r.caminho_pdf) : null;

          const card = el("div", { class: "card mb-2" }, [
            el("div", { class: "card-body" }, [
              el("div", { class: "d-flex justify-content-between align-items-center" }, [
                el("div", {}, [
                  el("h6", { class: "mb-1" }, [fmt.text(r.nome_formula)]),
                  el("div", { class: "small text-muted" }, [
                    `Código: ${fmt.text(r.codigo_formula)} · Cat: ${fmt.text(r.categoria)}`
                  ])
                ]),
                el("div", { class: "text-end small text-muted" }, [
                  fmt.text(r.data_criacao_formatada || r.data_criacao || "")
                ])
              ]),
              el("div", { class: "mt-2 d-flex flex-wrap gap-2" }, [
                el("a", { href: viewHref, class: "btn btn-sm btn-outline-primary" }, ["Abrir"]),
                el("a", { href: editHref, class: "btn btn-sm btn-outline-warning" }, ["Editar"]),
                el("button", { type: "button", class: "btn btn-sm btn-outline-danger btn-open-delete", dataset: { id: String(r.id ?? "") } }, ["Excluir"]),
                pdfHref
                  ? el("a", { href: pdfHref, target: "_blank", class: "btn btn-sm btn-outline-secondary" }, ["PDF"])
                  : el("span", { class: "small text-muted align-self-center" }, ["Sem PDF"])
              ])
            ])
          ]);
          areaResultados.appendChild(card);
        });
      }
    };

    // ---------- Busca AJAX ----------
    let abortCtrl;
    const buscar = async () => {
      const fd = new FormData(form);
      const params = new URLSearchParams();
      for (const [k, v] of fd.entries()) params.append(k, v);

      if (abortCtrl) abortCtrl.abort();
      abortCtrl = new AbortController();

      const url = (form.getAttribute("action") || "api_buscar_formulas.php") + "?" + params.toString();
      if (btnPesquisar) { btnPesquisar.disabled = true; btnPesquisar.dataset.loading = "1"; }

      try {
        const resp = await fetch(url, {
          headers: { "X-Requested-With": "XMLHttpRequest" },
          cache: "no-store",
          signal: abortCtrl.signal
        });
        const text = await resp.text();
        let data;
        try { data = JSON.parse(text); }
        catch (e) { console.error("Resposta não é JSON válido. Recebido:", text); throw e; }
        renderResultados(data);
      } catch (err) {
        if (err.name !== "AbortError") {
          console.error("Falha na busca AJAX:", err);
          if (areaResultados && !tbody) areaResultados.innerHTML = '<div class="text-danger">Erro ao carregar resultados.</div>';
        }
      } finally {
        if (btnPesquisar) { btnPesquisar.disabled = false; delete btnPesquisar.dataset.loading; }
      }
    };

    // submit e auto-busca
    form.addEventListener("submit", (e) => { e.preventDefault(); buscar(); });
    $$("input, select", form).forEach((inp) => {
      const h = debounce(() => buscar(), 350);
      inp.addEventListener("input", h);
      inp.addEventListener("change", h);
    });
    // buscar(); // (opcional) dispara ao carregar

    // ---------- Exclusão com modal + AJAX ----------
    const confirmDeleteModalEl = document.getElementById("confirmDeleteModal");
    const confirmDeleteModal = safeBootstrapModal(confirmDeleteModalEl);
    const btnConfirmDelete = document.getElementById("btn-confirm-delete");
    const formDelete = document.getElementById("form-delete");
    const deleteIdInput = document.getElementById("delete_id");
    const deleteIdInputAlt = document.getElementById("delete_formula_id");
    const deleteIdInputAlt2 = document.getElementById("id_formulacao");
    const deleteIdInputAlt3 = document.getElementById("formulacao_id");

    document.addEventListener("click", (e) => {
      const btn = e.target.closest(".btn-open-delete");
      if (!btn) return;
      const id = btn.dataset.id || btn.getAttribute("data-id") || "";
      if (deleteIdInput) deleteIdInput.value = id;
      if (deleteIdInputAlt) deleteIdInputAlt.value = id;
      if (deleteIdInputAlt2) deleteIdInputAlt2.value = id;
      if (deleteIdInputAlt3) deleteIdInputAlt3.value = id;
      if (confirmDeleteModal) confirmDeleteModal.show();
      else if (formDelete) formDelete.submit();
    });

    if (btnConfirmDelete && formDelete) {
      btnConfirmDelete.addEventListener("click", async () => {
        const id =
          (deleteIdInput && deleteIdInput.value) ||
          (deleteIdInputAlt && deleteIdInputAlt.value) ||
          (deleteIdInputAlt2 && deleteIdInputAlt2.value) ||
          (deleteIdInputAlt3 && deleteIdInputAlt3.value) || "";

        if (!id) { alert("ID inválido."); return; }

        const qs = new URLSearchParams({ id, formula_id: id, id_formulacao: id, formulacao_id: id }).toString();
        const fd = new FormData();
        fd.append("id", id); fd.append("formula_id", id); fd.append("id_formulacao", id); fd.append("formulacao_id", id);

        try {
          const resp = await fetch((formDelete.action || "deletar_formula.php") + "?" + qs, {
            method: "POST",
            headers: { "X-Requested-With": "XMLHttpRequest" },
            body: fd
          });
          const text = await resp.text();
          let data; try { data = JSON.parse(text); } catch { data = null; }

          if (confirmDeleteModal) confirmDeleteModal.hide();

          if (data && data.success) buscar();
          else alert(data?.message || "Falha ao excluir.");
        } catch (err) {
          if (confirmDeleteModal) confirmDeleteModal.hide();
          console.error("Erro ao excluir:", err);
          alert("Não foi possível excluir agora.");
        }
      });
    }
  })();

  // ========================================================
  // 2) PÁGINA CRIAR/EDITAR FORMULA — Partes → Fases → Ingredientes
  // ========================================================
  (function initCriarEditar() {
    const form = document.getElementById("formula-form");
    if (!form) return;

    // campo oculto "acao"
    let acao = document.getElementById("acao");
    if (!acao) { acao = el("input", { type: "hidden", name: "acao", id: "acao", value: "" }); form.appendChild(acao); }

    const btnOpenModal = document.getElementById("btn-submit-modal");
    const modalEl = document.getElementById("confirmSubmitModal");
    const modal = safeBootstrapModal(modalEl);
    const btnSalvar = document.getElementById("btn-salvar-sair");
    const btnGerarPdf = document.getElementById("btn-gerar-pdf");

    if (btnOpenModal) {
      btnOpenModal.addEventListener("click", () => {
        if (!form.checkValidity()) { form.reportValidity(); return; }
        if (modal) modal.show();
        else {
          const escolha = window.confirm("OK = GERAR PDF | Cancelar = SALVAR E SAIR");
          if (escolha) { acao.value = "gerar_pdf"; form.target = "_blank"; form.submit(); form.removeAttribute("target"); }
          else { acao.value = "salvar_sair"; form.removeAttribute("target"); form.submit(); }
        }
      });
    }
    if (btnSalvar) btnSalvar.addEventListener("click", () => { acao.value = "salvar_sair"; form.removeAttribute("target"); form.submit(); });
    if (btnGerarPdf) btnGerarPdf.addEventListener("click", () => { acao.value = "gerar_pdf"; form.target = "_blank"; form.submit(); form.removeAttribute("target"); });

    // -------- Ativos em Destaque --------
    const ativosContainer = document.getElementById("ativos-container");
    const btnAddAtivo = document.getElementById("add-ativo");

    const addAtivoRow = (nome = "", desc = "") => {
      if (!ativosContainer) return;

      const row = el("div", { class: "row g-3 align-items-end mb-2 ativo-row" }, [
        el("div", { class: "col-md-5" }, [
          el("label", { class: "form-label" }, ["Nome do Ativo"]),
          el("input", { class: "form-control", type: "text", name: "ativos_nome[]", value: nome })
        ]),
        el("div", { class: "col-md-6" }, [
          el("label", { class: "form-label" }, ["Descrição"]),
          // textarea auto-ajustável
          (() => {
            const ta = document.createElement('textarea');
            ta.className = 'form-control';
            ta.name = 'ativos_desc[]';
            ta.setAttribute('rows', '1');
            ta.setAttribute('data-autogrow', '');
            ta.value = desc || '';
            return ta;
          })()
        ]),
        el("div", { class: "col-md-1 d-grid" }, [
          el("button", { type: "button", class: "btn btn-outline-danger btn-remove-ativo" }, ["Remover"])
        ])
      ]);

      ativosContainer.appendChild(row);
      // ativa o auto-resize nos elementos recém-criados
      __wireAutogrow(row);
    };

    if (btnAddAtivo) btnAddAtivo.addEventListener("click", () => addAtivoRow());
    if (ativosContainer) ativosContainer.addEventListener("click", (e) => { const b = e.target.closest(".btn-remove-ativo"); if (b) b.closest(".ativo-row")?.remove(); });

    // -------- Partes → Fases → Ingredientes --------
    const partsContainer = document.getElementById("sub-formulacoes-container");
    const btnAddPart = document.getElementById("add-sub-formulacao");

    let partIndex = 0;

    // Recalcula o total % da FASE
    const recalcPhaseTotal = (phaseCard) => {
      const pctInputs = $$("input[name$='[percentual][]']", phaseCard);
      const total = pctInputs.reduce((acc, inp) => acc + toNumber(inp.value), 0);
      const totalEl = $(".total-percent", phaseCard);
      if (totalEl) {
        totalEl.textContent = `Total: ${total.toFixed(2)}%`;
        totalEl.classList.toggle("text-danger", Math.abs(total - 100) > 0.01);
      }
    };

    // Adiciona uma LINHA de ingrediente numa FASE
    const addIngredientRow = (phaseCard, pIdx, fIdx) => {
      const tbody = $(`tbody[data-phase='${fIdx}']`, phaseCard);
      if (!tbody) return;

      const tr = el("tr", {}, [
        el("td", {}, [
          el("input", {
            type: "text", class: "form-control",
            name: `sub_formulacoes[${pIdx}][fases][${fIdx}][ingredientes][materia_prima][]`,
            placeholder: "Matéria-prima"
          })
        ]),
        el("td", {}, [
          // INCI Name as auto-expanding textarea
          el("textarea", {
            class: "form-control", rows: "1", "data-autogrow": "",
            name: `sub_formulacoes[${pIdx}][fases][${fIdx}][ingredientes][inci_name][]`,
            placeholder: "INCI Name"
          })
        ]),
        el("td", {}, [
          // Grupo INPUT + QSP
          el("div", { class: "input-group mb-1" }, [
            el("input", {
              type: "number", step: "0.01", min: "0",
              class: "form-control text-end pct-input",
              name: `sub_formulacoes[${pIdx}][fases][${fIdx}][ingredientes][percentual][]`,
              placeholder: "0.00", inputmode: "decimal"
            }),
            el("span", { class: "input-group-text" }, ["%"])
          ]),
          el("div", { class: "form-check form-check-inline small" }, [
            // Hidden input to ensure array synchronization (always sends 0 or 1)
            el("input", {
              type: "hidden",
              class: "qsp-hidden",
              name: `sub_formulacoes[${pIdx}][fases][${fIdx}][ingredientes][qsp][]`,
              value: "0"
            }),
            // Visible checkbox
            el("input", {
              type: "checkbox",
              class: "form-check-input qsp-check",
              id: `qsp-${pIdx}-${fIdx}-${Date.now()}`
            }),
            el("label", { class: "form-check-label text-muted", for: `qsp-${pIdx}-${fIdx}-${Date.now()}` }, ["QSP"])
          ])
        ]),
        el("td", { class: "text-center" }, [
          el("button", { type: "button", class: "btn btn-sm btn-outline-danger btn-remove-ingrediente" }, ["Remover"])
        ])
      ]);

      tbody.appendChild(tr);

      // Ativa o auto-resize nos textareas (INCI Name e outros)
      if (typeof window.__applyAutogrow === 'function') {
        window.__applyAutogrow(tr);
      } else if (typeof window.__wireAutogrow === 'function') { // Fallback, just in case
        window.__wireAutogrow(tr);
      }

      // Eventos QSP
      const qspCheck = $(".qsp-check", tr);
      const qspHidden = $(".qsp-hidden", tr);
      const pctInput = $(".pct-input", tr);

      if (qspCheck && pctInput && qspHidden) {
        qspCheck.addEventListener("change", (e) => {
          const isChecked = e.target.checked;
          qspHidden.value = isChecked ? "1" : "0"; // Update hidden input

          if (isChecked) {
            pctInput.value = "";
            pctInput.disabled = true;
            pctInput.placeholder = "QSP";
          } else {
            pctInput.disabled = false;
            pctInput.placeholder = "0.00";
          }
          recalcPhaseTotal(phaseCard);
        });

        pctInput.addEventListener("input", () => recalcPhaseTotal(phaseCard));
      }
    };

    // Adiciona uma FASE dentro de uma PARTE
    const addPhaseCard = (partCard, pIdx) => {
      const phasesContainer = $(".phases-container", partCard);
      if (!phasesContainer) return;
      const fIdx = Number(phasesContainer.dataset.nextIndex || "0");
      phasesContainer.dataset.nextIndex = String(fIdx + 1);

      const phaseCard = el("div", { class: "card border-0 mb-3 shadow-sm phase-card", dataset: { fIdx } }, [
        el("div", { class: "card-header d-flex align-items-center justify-content-between" }, [
          el("div", { class: "d-flex align-items-center gap-2" }, [
            el("i", { class: "bi bi-diagram-3" }),
            el("strong", {}, ["Fase"])
          ]),
          el("div", { class: "d-flex align-items-center gap-2" }, [
            el("span", { class: "small text-muted total-percent" }, ["Total: 0.00%"]),
            el("button", { type: "button", class: "btn btn-sm btn-outline-danger btn-remove-phase" }, ["Remover Fase"])
          ])
        ]),
        el("div", { class: "card-body p-3" }, [
          el("div", { class: "row g-3 mb-2" }, [
            el("div", { class: "col-md-6" }, [
              el("label", { class: "form-label" }, ["Nome da Fase"]),
              el("input", {
                type: "text", class: "form-control",
                name: `sub_formulacoes[${pIdx}][fases][${fIdx}][nome]`,
                placeholder: "Ex.: Fase A, Fase B..."
              })
            ])
          ]),
          el("div", { class: "table-responsive" }, [
            el("table", { class: "table table-sm align-middle mb-2" }, [
              el("thead", {}, [
                el("tr", {}, [
                  el("th", {}, ["Matéria-prima"]),
                  el("th", {}, ["INCI Name"]),
                  el("th", { class: "text-end" }, ["%"]),
                  el("th", { class: "text-center", style: "width: 90px;" }, ["Ações"])
                ])
              ]),
              el("tbody", { dataset: { phase: String(fIdx) } })
            ])
          ]),
          el("div", { class: "d-flex justify-content-end" }, [
            el("button", { type: "button", class: "btn btn-outline-primary btn-add-ingrediente", dataset: { pIdx: String(pIdx), fIdx: String(fIdx) } }, ["Adicionar Ingrediente"])
          ])
        ])
      ]);

      phasesContainer.appendChild(phaseCard);
      addIngredientRow(phaseCard, pIdx, fIdx);
    };

    // Adiciona uma PARTE
    const addPartCard = () => {
      if (!partsContainer) return;
      const pIdx = partIndex++;

      const partCard = el("div", { class: "card shadow-sm mb-3 part-card", dataset: { pIdx } }, [
        el("div", { class: "card-header d-flex align-items-center justify-content-between" }, [
          el("div", { class: "d-flex align-items-center gap-2" }, [
            el("i", { class: "bi bi-diagram-3" }),
            el("strong", {}, ["Parte da Formulação"])
          ]),
          el("div", {}, [
            el("button", { type: "button", class: "btn btn-sm btn-outline-danger btn-remove-part" }, ["Remover Parte"])
          ])
        ]),
        el("div", { class: "card-body p-3" }, [
          el("div", { class: "row g-3 mb-3" }, [
            el("div", { class: "col-md-6" }, [
              el("label", { class: "form-label" }, ["Nome da Parte"]),
              el("input", {
                type: "text", class: "form-control",
                name: `sub_formulacoes[${pIdx}][nome]`,
                placeholder: "Ex.: Base, Ativos, Fragrância..."
              })
            ]),
            el("div", { class: "col-12" }, [
              el("label", { class: "form-label" }, ["Modo de Preparo (opcional)"]),
              el("textarea", {
                rows: 3, class: "form-control",
                name: `sub_formulacoes[${pIdx}][modo_preparo]`,
                placeholder: "Descreva o modo de preparo desta parte..."
              })
            ])
          ]),
          // container das fases desta parte
          el("div", { class: "phases-container", dataset: { nextIndex: "0" } }),
          el("div", { class: "d-flex justify-content-end mt-2" }, [
            el("button", { type: "button", class: "btn btn-outline-success btn-add-phase", dataset: { pIdx: String(pIdx) } }, ["Adicionar Fase"])
          ])
        ])
      ]);

      partsContainer.appendChild(partCard);
      // inicia com uma FASE padrão
      addPhaseCard(partCard, pIdx);
    };

    if (btnAddPart) btnAddPart.addEventListener("click", () => addPartCard());

    // Delegação de eventos dentro de Partes/Fases
    if (partsContainer) {
      partsContainer.addEventListener("click", (e) => {
        const btn = e.target;

        // Remover Parte
        const btnRemPart = btn.closest(".btn-remove-part");
        if (btnRemPart) {
          btnRemPart.closest(".part-card")?.remove();
          return;
        }

        // Adicionar Fase
        const btnAddPhase = btn.closest(".btn-add-phase");
        if (btnAddPhase) {
          const partCard = btnAddPhase.closest(".part-card");
          const pIdx = Number(partCard?.dataset.pIdx || "0");
          addPhaseCard(partCard, pIdx);
          return;
        }

        // Remover Fase
        const btnRemPhase = btn.closest(".btn-remove-phase");
        if (btnRemPhase) {
          btnRemPhase.closest(".phase-card")?.remove();
          return;
        }

        // Adicionar Ingrediente
        const btnAddIng = btn.closest(".btn-add-ingrediente");
        if (btnAddIng) {
          const phaseCard = btnAddIng.closest(".phase-card");
          const partCard = btnAddIng.closest(".part-card");
          const pIdx = Number(partCard?.dataset.pIdx || "0");
          const fIdx = Number(phaseCard?.dataset.fIdx || "0");
          addIngredientRow(phaseCard, pIdx, fIdx);
          return;
        }

        // Remover Ingrediente
        const btnRemIng = btn.closest(".btn-remove-ingrediente");
        if (btnRemIng) {
          const phaseCard = btnRemIng.closest(".phase-card");
          btnRemIng.closest("tr")?.remove();
          if (phaseCard) recalcPhaseTotal(phaseCard);
        }
      });

      // Recalcular total da Fase ao digitar %
      partsContainer.addEventListener("input", (e) => {
        const inp = e.target;
        if (inp && inp.name && inp.name.includes("[ingredientes][percentual][]")) {
          const phaseCard = inp.closest(".phase-card");
          if (phaseCard) recalcPhaseTotal(phaseCard);
        }
      });
    }

    // Evitar Enter submeter sem querer
    form.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        const isTextarea = e.target && e.target.tagName === "TEXTAREA";
        const isInsideModal = e.target && e.target.closest(".modal");
        if (!isTextarea && !isInsideModal) e.preventDefault();
      }
    });

    // Inicializações padrão (opcional)
    if (document.getElementById("ativos-container")?.children.length === 0) addAtivoRow();
    if (partsContainer?.children.length === 0) addPartCard();
  })();

  // === AUTO-GROW PARA TEXTAREAS ===
  function autogrowTextareas(root) {
    (root || document).querySelectorAll('textarea[data-autogrow]').forEach((ta) => {
      const grow = () => {
        ta.style.height = 'auto';
        ta.style.height = ta.scrollHeight + 'px';
      };
      // evita múltiplos ouvintes ao reusar o template
      ta.removeEventListener('input', grow);
      ta.addEventListener('input', grow);
      // aplica já com conteúdo carregado
      requestAnimationFrame(grow);
    });
  }

  // roda ao carregar a página
  document.addEventListener('DOMContentLoaded', () => autogrowTextareas(document));

  // EXPOR para usar após inserir novos nós por JS
  window.__applyAutogrow = autogrowTextareas;

  function __wireAutogrow(root = document) {
    root.querySelectorAll('textarea[data-autogrow]').forEach(ta => {
      const grow = () => { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; };
      ta.removeEventListener('input', grow);
      ta.addEventListener('input', grow);
      requestAnimationFrame(grow); // ajusta já com valor inicial
    });
  }
  // ao carregar a página
  document.addEventListener('DOMContentLoaded', () => __wireAutogrow(document));
})();
