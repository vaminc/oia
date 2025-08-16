document.addEventListener('DOMContentLoaded', () => {
    // Elementos da UI
    const navButtons = document.querySelectorAll('.nav-btn');
    const categoriesContainer = document.getElementById('categories-container');
    const gridContainer = document.getElementById('grid-container');
    const loadingOverlay = document.getElementById('loading-overlay');
    const playerModal = document.getElementById('player-modal');
    const closeModalBtn = document.querySelector('.close-btn');
    const searchInput = document.getElementById('search-input');
    
    const videoPlayerElement = document.getElementById('video-player');
    const playerContainer = videoPlayerElement.parentElement;
    const playerTitle = document.getElementById('player-title');
    const seriesInfoContainer = document.getElementById('series-info');
    const episodesList = document.getElementById('episodes-list');

    let shakaPlayer = null;
    let currentContentType = 'live';
    let allStreamsCache = []; // Cache para guardar os streams da categoria selecionada

    // Inicializa o LazyLoad
    const lazyLoadInstance = new LazyLoad({
        elements_selector: ".lazy"
    });

    // --- Funções Principais ---

    const showLoading = (show) => {
        loadingOverlay.style.display = show ? 'flex' : 'none';
    };

    const fetchAPI = async (params) => {
        showLoading(true);
        try {
            const response = await fetch(`api.php?${params}`);
            if (!response.ok) throw new Error(`Erro na rede: ${response.statusText}`);
            const data = await response.json();
            if (data.status !== 'success') throw new Error(data.message || 'Erro na API');
            return data;
        } catch (error) {
            console.error('Falha na chamada da API:', error);
            alert(`Erro: ${error.message}`);
            return null;
        } finally {
            showLoading(false);
        }
    };

    // --- Lógica do Shaka Player (com configuração avançada) ---
    const initShakaPlayer = async () => {
        shaka.polyfill.installAll();
        if (!shaka.Player.isBrowserSupported()) {
            console.error('Shaka Player não é suportado neste navegador.');
            return;
        }

        shakaPlayer = new shaka.Player(videoPlayerElement);
        
        // ==================================================================
        // CONFIGURAÇÃO AVANÇADA DO PLAYER
        // ==================================================================
        shakaPlayer.configure({
            // Aumenta a tolerância a lacunas no stream. Essencial para IPTV.
            streaming: {
                gapDetectionThreshold: 0.5,
                jumpLargeGaps: true,
                stallEnabled: true,
                stallThreshold: 1,
                stallSkip: 0.1
            },
            // Configurações de manifesto para HLS
            manifest: {
                hls: {
                    // Permite que o player ignore o timestamp do áudio se ele estiver causando problemas.
                    ignoreTextStreamFailures: true,
                    useFullSegmentsForTimestamp: true
                }
            },
            // Aumenta o timeout para requisições de segmentos
            networking: {
                requestTimeout: 30 // 30 segundos
            }
        });
        // ==================================================================

        new shaka.ui.Overlay(shakaPlayer, playerContainer, videoPlayerElement);
        shakaPlayer.addEventListener('error', (e) => {
            // Log de erro melhorado para depuração
            console.error('Erro detalhado do Shaka Player:', JSON.stringify(e.detail, null, 2));
            alert(`Erro no Player: Código ${e.detail.code} - Categoria ${e.detail.category}. Verifique o console para detalhes.`);
        });
    };

    const playStream = async (url) => {
        if (!shakaPlayer) return;
        try {
            await shakaPlayer.load(url);
        } catch (error) {
            console.error('Erro ao carregar o stream:', error);
            alert('Não foi possível carregar este conteúdo. Verifique o console para mais detalhes.');
        }
    };

    const stopPlayer = () => {
        if (shakaPlayer) shakaPlayer.unload();
    };

    // --- Renderização e Lógica da UI (com correção de imagens) ---

    const renderGridItems = (items) => {
        gridContainer.innerHTML = '';
        if (!items || items.length === 0) {
            gridContainer.innerHTML = '<p>Nenhum item encontrado.</p>';
            return;
        }
        items.forEach(stream => {
            const item = document.createElement('div');
            item.className = 'grid-item';
            item.dataset.streamId = stream.stream_id || stream.series_id;
            item.dataset.type = currentContentType;

            // LÓGICA DE IMAGEM CORRIGIDA:
            // Tenta pegar a imagem de múltiplos campos possíveis.
            const imageUrl = stream.stream_icon || stream.icon || (stream.info ? stream.info.movie_image : null) || 'https://via.placeholder.com/180x270.png?text=Sem+Imagem';

            const posterImg = document.createElement('img' );
            posterImg.className = 'poster lazy';
            posterImg.setAttribute('data-src', imageUrl);
            posterImg.alt = stream.name;
            // Adiciona um fallback caso a imagem falhe em carregar
            posterImg.onerror = () => { 
                posterImg.onerror = null; // Evita loop de erro
                posterImg.src = 'https://via.placeholder.com/180x270.png?text=Erro'; 
            };
            
            const title = document.createElement('div' );
            title.className = 'title';
            title.textContent = stream.name;

            item.appendChild(posterImg);
            item.appendChild(title);
            gridContainer.appendChild(item);
        });

        // Atualiza o LazyLoad e anima os itens
        lazyLoadInstance.update();
        anime({
            targets: '.grid-item',
            translateY: [20, 0],
            opacity: [0, 1],
            delay: anime.stagger(50),
            easing: 'easeOutQuad'
        });
    };

    const loadCategories = async (contentType) => {
        const response = await fetchAPI(`action=get_categories&content_type=${contentType}`);
        categoriesContainer.innerHTML = '';
        if (response && response.data) {
            const allItem = document.createElement('div');
            allItem.className = 'category-item active';
            allItem.textContent = 'Todas as Categorias';
            allItem.dataset.categoryId = 'all';
            categoriesContainer.appendChild(allItem);

            response.data.forEach(cat => {
                const item = document.createElement('div');
                item.className = 'category-item';
                item.textContent = cat.category_name;
                item.dataset.categoryId = cat.category_id;
                categoriesContainer.appendChild(item);
            });
        }
    };

    const loadStreams = async (contentType, categoryId) => {
        const catParam = (categoryId === 'all' || contentType === 'live') ? '' : `&category_id=${categoryId}`;
        const response = await fetchAPI(`action=get_streams&content_type=${contentType}${catParam}`);
        allStreamsCache = response ? response.data : [];
        renderGridItems(allStreamsCache);
    };

    const openPlayer = async (streamId, type) => {
        playerTitle.textContent = 'Carregando...';
        seriesInfoContainer.style.display = 'none';
        episodesList.innerHTML = '';
        playerModal.style.display = 'flex';

        if (type === 'series') {
            const seriesData = await fetchAPI(`action=get_series_info&series_id=${streamId}`);
            if (seriesData && seriesData.data) {
                playerTitle.textContent = seriesData.data.info.name;
                seriesInfoContainer.style.display = 'block';
                const seasons = seriesData.data.episodes;
                for (const seasonNum in seasons) {
                    seasons[seasonNum].forEach(episode => {
                        const epItem = document.createElement('div');
                        epItem.className = 'episode-item';
                        epItem.textContent = `S${seasonNum}E${episode.episode_num} - ${episode.title}`;
                        epItem.dataset.streamId = episode.id;
                        episodesList.appendChild(epItem);
                    });
                }
                if (episodesList.firstChild) episodesList.firstChild.click();
            }
        } else {
            const urlData = await fetchAPI(`action=get_stream_url&content_type=${type}&stream_id=${streamId}`);
            if (urlData && urlData.data.stream_url) {
                playStream(urlData.data.stream_url);
                const gridItem = document.querySelector(`.grid-item[data-stream-id='${streamId}']`);
                playerTitle.textContent = gridItem ? gridItem.querySelector('.title').textContent : 'Player';
            }
        }
    };

    // --- Event Listeners ---
    navButtons.forEach(button => {
        button.addEventListener('click', async () => {
            navButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            currentContentType = button.dataset.type;
            searchInput.value = '';
            await loadCategories(currentContentType);
            await loadStreams(currentContentType, 'all');
        });
    });

    categoriesContainer.addEventListener('click', async (e) => {
        if (e.target.classList.contains('category-item')) {
            document.querySelectorAll('.category-item').forEach(item => item.classList.remove('active'));
            e.target.classList.add('active');
            searchInput.value = '';
            await loadStreams(currentContentType, e.target.dataset.categoryId);
        }
    });

    gridContainer.addEventListener('click', (e) => {
        const gridItem = e.target.closest('.grid-item');
        if (gridItem) openPlayer(gridItem.dataset.streamId, gridItem.dataset.type);
    });

    episodesList.addEventListener('click', async (e) => {
        if (e.target.classList.contains('episode-item')) {
            document.querySelectorAll('.episode-item').forEach(item => item.classList.remove('active'));
            e.target.classList.add('active');
            const urlData = await fetchAPI(`action=get_stream_url&content_type=series&stream_id=${e.target.dataset.streamId}`);
            if (urlData && urlData.data.stream_url) playStream(urlData.data.stream_url);
        }
    });

    searchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        if (!allStreamsCache) return;
        const filteredStreams = allStreamsCache.filter(stream => stream.name.toLowerCase().includes(searchTerm));
        renderGridItems(filteredStreams);
    });

    closeModalBtn.addEventListener('click', () => {
        playerModal.style.display = 'none';
        stopPlayer();
    });

    // --- Inicialização ---
    const init = async () => {
        await initShakaPlayer();
        await loadCategories(currentContentType);
        await loadStreams(currentContentType, 'all');
    };

    init();
});
