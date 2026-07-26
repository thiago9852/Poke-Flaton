window.toggleFollowTrainer = async function (username) {
    const btn = document.getElementById('follow-btn');
    if (!btn) return;

    btn.disabled = true;
    const formData = new FormData();
    formData.append('username', username);

    // Resgata o link passado pelo data-attribute
    const dataEl = document.getElementById('public-profile-data');
    const toggleUrl = dataEl ? dataEl.dataset.urlFollowToggle : '';

    try {
        const response = await fetch(toggleUrl, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            const btnText = btn.querySelector('.btn-text');
            const btnIcon = btn.querySelector('i');
            const countDisplay = document.getElementById('followers-count-display');

            if (data.following) {
                btn.className = "btn btn-secondary btn-block";
                btnText.textContent = "Deixar de Seguir";
                btnIcon.className = "fa-solid fa-user-minus";
                if (countDisplay) countDisplay.textContent = parseInt(countDisplay.textContent) + 1;
            } else {
                btn.className = "btn btn-primary btn-block";
                btnText.textContent = "Seguir Treinador";
                btnIcon.className = "fa-solid fa-user-plus";
                if (countDisplay) countDisplay.textContent = parseInt(countDisplay.textContent) - 1;
            }
        } else {
            alert(data.error || 'Erro ao realizar ação.');
        }
    } catch (e) {
        console.error(e);
        alert('Erro de conexão ao servidor.');
    } finally {
        btn.disabled = false;
    }
};