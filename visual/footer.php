</div>
        </div>
    </div>

    <style>
        a:focus,
        a:active,
        button:focus,
        button:active {
            outline: 0 !important;
            box-shadow: none !important;
        }

        #msbo,
        #msbo:hover,
        #msbo:focus,
        #msbo:active,
        #msbo .ic,
        #msbo .fa-bars {
            color: #673ab7 !important;
            outline: 0 !important;
            box-shadow: none !important;
        }

        #msbo:hover,
        #msbo:hover .ic,
        #msbo:hover .fa-bars {
            color: #4d2c91 !important;
        }

        .mcw {
            transition: opacity 0.16s ease, transform 0.16s ease;
        }

        .mcw.page-leaving {
            opacity: 0;
            transform: translateY(3px);
        }

        .mcw.page-entering {
            opacity: 0;
            transform: translateY(3px);
        }
    </style>

    <script>
    (function () {
        const mcw = document.querySelector(".mcw");
        if (!mcw) return;

        // 1. Toggle original del sidebar con #msbo usando body.msb-x
        document.addEventListener("click", function (evento) {
            const btnMsbo = evento.target.closest("#msbo");
            if (!btnMsbo) return;

            evento.preventDefault();
            evento.stopPropagation();
            if (typeof btnMsbo.blur === "function") btnMsbo.blur();

            document.body.classList.toggle("msb-x");
        }, true);

        // 2. Toggle confiable para descripciones colapsables de productos dentro de .mcw
        document.addEventListener("click", function (evento) {
            const triggerDesc = evento.target.closest('.mcw [data-toggle="collapse"], .mcw a[data-target]');
            if (!triggerDesc) return;

            const selectorTarget = triggerDesc.getAttribute("data-target") || triggerDesc.getAttribute("href");
            if (!selectorTarget || !selectorTarget.startsWith("#")) return;

            const targetEl = document.querySelector(selectorTarget);
            if (!targetEl) return;

            evento.preventDefault();
            if (typeof triggerDesc.blur === "function") triggerDesc.blur();

            if (window.jQuery && typeof jQuery.fn.collapse === "function") {
                jQuery(targetEl).collapse("toggle");
            } else {
                const abierta = targetEl.classList.contains("in");
                targetEl.classList.toggle("in", !abierta);
                targetEl.style.display = abierta ? "none" : "block";
            }
        });

        // 3. Respaldo para abrir modal de Realizar venta si se disparó por AJAX
        document.addEventListener("click", function (evento) {
            const btnVenta = evento.target.closest('.mcw [data-target="#modalRealizarVenta"], .mcw #btnAbrirVenta, .mcw #btnRealizarVentaModal');
            if (!btnVenta) return;

            const modalEl = document.getElementById("modalRealizarVenta");
            if (!modalEl) return;

            if (modalEl.parentNode !== document.body) {
                document.body.appendChild(modalEl);
            }

            if (window.jQuery && typeof jQuery.fn.modal === "function") {
                jQuery("#modalRealizarVenta").modal("show");
            } else {
                modalEl.classList.add("in");
                modalEl.style.display = "block";
            }
        });

        if (!window.fetch || !window.history || !window.history.pushState) return;

        let controladorActual = null;
        let estaNavegando = false;

        function esEnlaceNavegable(enlace, evento) {
            if (!enlace) return false;

            if (
                enlace.id === "msbo" ||
                enlace.closest("#msbo") ||
                enlace.id === "btnTogglePerfil" ||
                enlace.closest("#btnTogglePerfil") ||
                enlace.id === "btnAbrirModalCerrarSesion" ||
                enlace.id === "btnConfirmarCerrarSesion" ||
                enlace.hasAttribute("data-toggle") ||
                enlace.hasAttribute("data-target")
            ) {
                return false;
            }

            if (
                evento.defaultPrevented ||
                evento.metaKey ||
                evento.ctrlKey ||
                evento.shiftKey ||
                evento.altKey ||
                evento.button !== 0
            ) {
                return false;
            }

            if (enlace.getAttribute("target") === "_blank" || enlace.hasAttribute("download")) {
                return false;
            }

            const href = (enlace.getAttribute("href") || "").trim();
            if (!href || href.startsWith("#") || href.startsWith("javascript:") || href.includes("logout.php")) {
                return false;
            }

            const url = new URL(href, window.location.href);
            return url.origin === window.location.origin;
        }

        function limpiarModalesYBackdrops() {
            document.querySelectorAll(".modal-backdrop").forEach(function (el) {
                el.remove();
            });
            document.body.classList.remove("modal-open");
            document.body.style.paddingRight = "";

            if (window.jQuery && typeof jQuery.fn.modal === "function") {
                jQuery(".modal").modal("hide");
            }
        }

        function actualizarEstadoSidebar(urlDestino) {
            const archivoActual = (urlDestino.pathname.split("/").pop() || "inicio.php").toLowerCase();

            document.querySelectorAll(".msb .navbar-nav > li").forEach(function (li) {
                li.classList.remove("active");
                const link = li.querySelector(":scope > a[href]");
                if (!link) return;
                const hrefAttr = (link.getAttribute("href") || "").trim();
                if (!hrefAttr || hrefAttr.startsWith("#") || hrefAttr.startsWith("javascript:")) return;

                const archivoLink = new URL(hrefAttr, window.location.href).pathname.split("/").pop().toLowerCase();
                if (archivoLink === archivoActual) {
                    li.classList.add("active");
                }
            });

            const dropdownParent = document.getElementById("dropdown");
            const submenu = document.getElementById("dropdown-lvl1");
            const btnPerfil = document.getElementById("btnTogglePerfil");
            let enMisDatos = false;

            if (submenu) {
                submenu.querySelectorAll("a[href]").forEach(function (subLink) {
                    const liSub = subLink.closest("li");
                    if (liSub) liSub.classList.remove("active");
                    const subHref = (subLink.getAttribute("href") || "").trim();
                    if (!subHref || subHref.startsWith("#") || subHref.startsWith("javascript:")) return;

                    const archivoSub = new URL(subHref, window.location.href).pathname.split("/").pop().toLowerCase();
                    if (archivoSub === archivoActual) {
                        if (liSub) liSub.classList.add("active");
                        enMisDatos = true;
                    }
                });
            }

            if (dropdownParent && submenu) {
                if (enMisDatos) {
                    dropdownParent.classList.add("active");
                    submenu.classList.add("in");
                    if (btnPerfil) btnPerfil.setAttribute("aria-expanded", "true");
                } else {
                    dropdownParent.classList.remove("active");
                }
            }
        }

        function ejecutarScriptsDeVista(contenedor) {
            const scripts = contenedor.querySelectorAll("script");
            scripts.forEach(function (viejoScript) {
                const nuevoScript = document.createElement("script");
                Array.from(viejoScript.attributes).forEach(function (attr) {
                    nuevoScript.setAttribute(attr.name, attr.value);
                });
                // Aislamos en un bloque para evitar choques de const/let entre cargas AJAX
                const codigo = viejoScript.textContent.trim();
                nuevoScript.textContent = codigo ? "{\n" + codigo + "\n}" : "";
                viejoScript.parentNode.replaceChild(nuevoScript, viejoScript);
            });
        }

        async function navegarSuave(url, agregarHistorial) {
            if (agregarHistorial === undefined) agregarHistorial = true;

            if (controladorActual) {
                controladorActual.abort();
            }
            controladorActual = new AbortController();
            estaNavegando = true;

            limpiarModalesYBackdrops();
            mcw.classList.remove("page-entering");
            mcw.classList.add("page-leaving");

            try {
                const respuesta = await fetch(url.href, {
                    signal: controladorActual.signal,
                    headers: { "X-Requested-With": "XMLHttpRequest" }
                });

                if (respuesta.redirected && respuesta.url.includes("login.html")) {
                    window.location.href = respuesta.url;
                    return;
                }

                if (!respuesta.ok) {
                    window.location.href = url.href;
                    return;
                }

                const htmlTexto = await respuesta.text();
                const parser = new DOMParser();
                const docNuevo = parser.parseFromString(htmlTexto, "text/html");
                const nuevoMcw = docNuevo.querySelector(".mcw");

                if (!nuevoMcw) {
                    window.location.href = url.href;
                    return;
                }

                if (docNuevo.title) {
                    document.title = docNuevo.title;
                }

                mcw.innerHTML = nuevoMcw.innerHTML;

                if (agregarHistorial) {
                    window.history.pushState({ url: url.href }, "", url.href);
                }

                actualizarEstadoSidebar(new URL(window.location.href));

                mcw.classList.remove("page-leaving");
                mcw.classList.add("page-entering");
                void mcw.offsetWidth;
                mcw.classList.remove("page-entering");

                ejecutarScriptsDeVista(mcw);
                window.scrollTo({ top: 0, behavior: "auto" });
            } catch (err) {
                if (err && err.name === "AbortError") {
                    return;
                }
                window.location.href = url.href;
            } finally {
                estaNavegando = false;
            }
        }

        document.addEventListener("click", function (evento) {
            const enlace = evento.target.closest("a");
            if (!esEnlaceNavegable(enlace, evento)) return;

            const urlDestino = new URL(enlace.getAttribute("href"), window.location.href);
            if (urlDestino.href === window.location.href && !urlDestino.search && !estaNavegando) {
                evento.preventDefault();
                return;
            }

            evento.preventDefault();
            navegarSuave(urlDestino, true);
        });

        window.addEventListener("popstate", function () {
            navegarSuave(new URL(window.location.href), false);
        });
    })();
    </script>
</body>
</html>