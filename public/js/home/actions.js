export function initActions() {
    const detailButtons = document.querySelectorAll('.detail-button');
    const actionButtons = document.querySelectorAll('[data-action]');
    const adminRegionSelect = document.getElementById('adminRegionSelect');

    detailButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const item = this.dataset.item;
            const category = this.dataset.category;
            const list = this.dataset.list;
            alert(list + ': ' + item + ' (' + category + ')');
        });
    });

    actionButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const action = this.dataset.action;
            if (action === 'registration') {
                const selectedRegion = adminRegionSelect ? adminRegionSelect.options[adminRegionSelect.selectedIndex].text : 'wilayah terkait';
                alert('Pengajuan pendaftaran admin untuk ' + selectedRegion + ' sedang dalam pengembangan.');
                return;
            }
            alert('Fitur sedang dalam pengembangan');
        });
    });
}
