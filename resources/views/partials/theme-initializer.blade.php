<script>
    (function () {
        try {
            if (window.localStorage.getItem('ione_theme') === 'dark') {
                document.documentElement.classList.add('theme-dark');
            }
        } catch (error) {
        }
    })();
</script>
