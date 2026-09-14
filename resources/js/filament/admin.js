function registerHighlightAttribute() {
    if (!window.Trix || window.Trix.config.textAttributes.highlight) {
        return;
    }

    window.Trix.config.textAttributes.highlight = {
        tagName: 'mark',
        inheritable: true,
    };
}

function addHighlightButton(event) {
    registerHighlightAttribute();

    const toolbar = event.target.toolbarElement;
    const textTools = toolbar?.querySelector('[data-trix-button-group="text-tools"]');

    if (!textTools || textTools.querySelector('[data-trix-attribute="highlight"]')) {
        return;
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'trix-button trix-button--icon';
    button.dataset.trixAttribute = 'highlight';
    button.title = 'Highlight';
    button.setAttribute('aria-label', 'Highlight');
    button.tabIndex = -1;
    button.innerHTML = '<span aria-hidden="true">H</span>';
    textTools.append(button);
}

registerHighlightAttribute();
document.addEventListener('trix-before-initialize', registerHighlightAttribute);
document.addEventListener('trix-initialize', addHighlightButton);
