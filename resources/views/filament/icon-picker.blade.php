<style>
    .fi-fpb-icon-option {
        display: grid;
        grid-template-columns: 1.5rem minmax(8rem, 1fr) auto;
        align-items: center;
        gap: 0.625rem;
        width: 100%;
    }

    .fi-fpb-icon-option-svg {
        width: 1.25rem;
        height: 1.25rem;
        color: currentColor;
    }

    .fi-fpb-icon-option code {
        overflow: hidden;
        color: rgb(107 114 128);
        font-size: 0.6875rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dark .fi-fpb-icon-option code {
        color: rgb(156 163 175);
    }

    .fi-fpb-navigation-child-icon > .fi-sidebar-item-btn > .fi-sidebar-item-icon {
        display: none !important;
    }

    .fi-fpb-navigation-child-icon > .fi-sidebar-item-btn > .fi-sidebar-item-grouped-border {
        display: none !important;
    }

    .fi-fpb-navigation-child-icon > .fi-sidebar-item-btn::before {
        display: block;
        flex: none;
        width: 1.25rem;
        height: 1.25rem;
        content: '';
        background-color: currentColor;
        -webkit-mask: var(--fi-fpb-child-icon) center / contain no-repeat;
        mask: var(--fi-fpb-child-icon) center / contain no-repeat;
    }
</style>
