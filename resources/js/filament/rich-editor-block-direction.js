export default function () {
    return window.FilamentRichEditor.tiptap.core.Extension.create({
        name: 'blockDirection',

        addGlobalAttributes() {
            return [{
                types: ['paragraph', 'heading', 'blockquote', 'listItem'],
                attributes: {
                    dir: {
                        default: null,
                        parseHTML: (element) => {
                            const direction = element.getAttribute('dir')
                            return ['rtl', 'ltr', 'auto'].includes(direction) ? direction : null
                        },
                    },
                },
            }]
        },
    })
}
