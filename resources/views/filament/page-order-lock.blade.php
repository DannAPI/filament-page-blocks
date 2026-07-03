<style>
    .fi-page-blocks-system-page[x-sortable-handle] {
        cursor: not-allowed;
        opacity: 0.8;
    }
</style>

<script>
    (() => {
        const preventSystemPageDrag = (event) => {
            const row = event.target.closest?.('.fi-page-blocks-system-page[x-sortable-handle]')

            if (! row) {
                return
            }

            event.preventDefault()
            event.stopImmediatePropagation()
        }

        document.addEventListener('pointerdown', preventSystemPageDrag, true)
        document.addEventListener('mousedown', preventSystemPageDrag, true)
        document.addEventListener('touchstart', preventSystemPageDrag, true)
    })()
</script>
