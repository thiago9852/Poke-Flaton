window.switchTab = function (evt, tabId) {
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    evt.currentTarget.classList.add('active');
};

window.toggleCategorySection = function (contentId, chevronId) {
    const content = document.getElementById(contentId);
    const chevron = document.getElementById(chevronId);
    if (content && chevron) {
        content.classList.toggle('expanded');
        chevron.classList.toggle('rotated');
    }
};