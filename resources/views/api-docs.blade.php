<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Party Planner API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui.css">
    <style>
        html {
            box-sizing: border-box;
            overflow-y: scroll;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }
        body {
            margin: 0;
            background: #fafafa;
        }
        .swagger-ui .topbar {
            display: none;
        }
        .custom-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .custom-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .custom-header p {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="custom-header">
        <h1>Party Planner API</h1>
        <p>Documentation interactive de l'API</p>
    </div>
    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-standalone-preset.js"></script>
    <script>
        // Plugin personnalisé pour capturer le token après login
        const TokenCapturePlugin = () => {
            return {
                statePlugins: {
                    spec: {
                        wrapSelectors: {
                            allowTryItOutFor: () => () => true
                        }
                    }
                }
            };
        };

        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: "/api/docs/openapi.yaml",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl,
                    TokenCapturePlugin
                ],
                layout: "StandaloneLayout",
                persistAuthorization: true,
                displayRequestDuration: true,
                filter: true,
                tryItOutEnabled: true,
                requestInterceptor: (request) => {
                    // Swagger UI devrait automatiquement ajouter le header Authorization
                    // si le token est défini via preauthorizeApiKey
                    return request;
                },
                responseInterceptor: (response) => {
                    // Capturer le token après une connexion réussie
                    if (response.url && response.url.includes('/auth/login') && response.status === 200) {
                        try {
                            // Parser la réponse
                            let responseData;
                            if (typeof response.body === 'string') {
                                responseData = JSON.parse(response.body);
                            } else if (response.data) {
                                responseData = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                            } else {
                                responseData = response.body;
                            }

                            if (responseData && responseData.token) {
                                // Définir le token dans l'autorisation
                                setTimeout(() => {
                                    if (window.ui && window.ui.preauthorizeApiKey) {
                                        window.ui.preauthorizeApiKey('bearerAuth', responseData.token);
                                        console.log('✅ Token d\'authentification défini automatiquement:', responseData.token.substring(0, 20) + '...');

                                        // Afficher une notification visuelle
                                        const authBtn = document.querySelector('.btn.authorize');
                                        if (authBtn) {
                                            authBtn.style.backgroundColor = '#4CAF50';
                                            authBtn.style.color = 'white';
                                            setTimeout(() => {
                                                authBtn.style.backgroundColor = '';
                                                authBtn.style.color = '';
                                            }, 2000);
                                        }
                                    }
                                }, 200);
                            }
                        } catch (e) {
                            console.error('❌ Erreur lors de la capture du token:', e, response);
                        }
                    }
                    return response;
                }
            });
            window.ui = ui;

            // Fonction pour extraire et définir le token
            function extractAndSetToken(responseText) {
                try {
                    const json = JSON.parse(responseText);
                    if (json && json.token && window.ui && window.ui.preauthorizeApiKey) {
                        window.ui.preauthorizeApiKey('bearerAuth', json.token);
                        console.log('✅ Token d\'authentification défini automatiquement');

                        // Notification visuelle
                        const authBtn = document.querySelector('.btn.authorize, .authorize');
                        if (authBtn) {
                            const originalBg = authBtn.style.backgroundColor;
                            authBtn.style.backgroundColor = '#4CAF50';
                            authBtn.style.color = 'white';
                            setTimeout(() => {
                                authBtn.style.backgroundColor = originalBg;
                                authBtn.style.color = '';
                            }, 2000);
                        }
                        return true;
                    }
                } catch (e) {
                    // Ce n'est pas du JSON valide ou pas de token
                }
                return false;
            }

            // Surveiller les réponses affichées dans Swagger UI
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            // Chercher les réponses de login
                            const responseBodies = node.querySelectorAll ? node.querySelectorAll('.response-body, .highlight-code, pre') : [];
                            responseBodies.forEach(function(el) {
                                const text = el.textContent || el.innerText;
                                if (text && text.includes('"token"') && text.includes('/auth/login')) {
                                    extractAndSetToken(text);
                                }
                            });

                            // Vérifier aussi directement le contenu du nœud
                            if (node.classList && (node.classList.contains('response-body') || node.classList.contains('highlight-code'))) {
                                const text = node.textContent || node.innerText;
                                if (text && text.includes('"token"')) {
                                    extractAndSetToken(text);
                                }
                            }
                        }
                    });
                });
            });

            // Démarrer l'observation après un court délai pour laisser Swagger UI se charger
            setTimeout(() => {
                const swaggerContainer = document.getElementById('swagger-ui');
                if (swaggerContainer) {
                    observer.observe(swaggerContainer, {
                        childList: true,
                        subtree: true
                    });
                }
            }, 1000);
        };
    </script>
</body>
</html>
