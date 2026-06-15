document.addEventListener('DOMContentLoaded', function() {
    const gridBtn = document.getElementById('bp-grid-view');
    const listBtn = document.getElementById('bp-list-view');
    const productList = document.querySelector('ul.products');

    if (!gridBtn || !listBtn || !productList) return;

    // Check stored preference
    const viewPref = localStorage.getItem('bp_shop_view_mode');
    
    if (viewPref === 'list') {
        setListView();
    } else {
        setGridView();
    }

    gridBtn.addEventListener('click', function(e) {
        e.preventDefault();
        setGridView();
        localStorage.setItem('bp_shop_view_mode', 'grid');
    });

    listBtn.addEventListener('click', function(e) {
        e.preventDefault();
        setListView();
        localStorage.setItem('bp_shop_view_mode', 'list');
    });

    function setGridView() {
        productList.classList.remove('bp-list-view');
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
    }

    function setListView() {
        productList.classList.add('bp-list-view');
        listBtn.classList.add('active');
        gridBtn.classList.remove('active');
    }
});
