/**
 * The Mac app's window only shows the app itself. A link to anywhere else, such as
 * one in a log, opens in the default browser, the way it would in any Mac app.
 */
export default function openExternalLinksInBrowser() {
    if (!document.documentElement.hasAttribute('data-desktop')) {
        return;
    }

    document.addEventListener(
        'click',
        (event) => {
            const link = event.target instanceof Element ? event.target.closest('a[href]') : null;

            if (!link || event.defaultPrevented) {
                return;
            }

            const url = new URL(link.href, window.location.href);

            if (url.origin === window.location.origin) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            fetch('/desktop/open-external', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ url: url.href }),
            });
        },
        true,
    );
}
