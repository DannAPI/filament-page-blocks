<style>
    .fi-fpb-navigation-dropdown > .fi-sidebar-item-btn {
        cursor: pointer;
    }

    .fi-fpb-navigation-dropdown > .fi-sidebar-item-btn::after {
        width: 0.45rem;
        height: 0.45rem;
        margin-inline-start: auto;
        content: '';
        border-right: 0.125rem solid currentColor;
        border-bottom: 0.125rem solid currentColor;
        opacity: 0.65;
        transform: rotate(45deg);
        transition: transform 150ms ease;
    }

    .fi-fpb-navigation-dropdown > .fi-sidebar-sub-group-items {
        max-height: 0;
        overflow: hidden;
        opacity: 0;
        transition: max-height 200ms ease, opacity 150ms ease;
    }

    .fi-fpb-navigation-dropdown.fi-fpb-navigation-dropdown-open > .fi-sidebar-item-btn::after {
        transform: rotate(225deg);
    }

    .fi-fpb-navigation-dropdown.fi-fpb-navigation-dropdown-open > .fi-sidebar-sub-group-items {
        max-height: 40rem;
        opacity: 1;
    }

    .fi-sidebar.fi-sidebar-open .fi-fpb-navigation-dropdown > .fi-sidebar-sub-group-items > .fi-sidebar-item > .fi-sidebar-item-btn {
        width: calc(100% - 2rem);
        margin-inline-start: 2rem;
    }
</style>
