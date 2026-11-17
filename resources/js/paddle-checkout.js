/**
 * Opens Paddle's checkout overlay, loading Paddle.js the first time it's needed
 * so the script only runs on the page where someone chooses to subscribe.
 *
 * @param {{ token: string, sandbox: boolean }} config The client-side token, and whether it's a sandbox one.
 * @param {object} options What Paddle.Checkout.open() takes: items, customer, customData and settings.
 */
let ready;

function loadPaddle({ token, sandbox }) {
    ready ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        // Paddle asks that Paddle.js is always loaded from its own CDN.
        script.src = 'https://cdn.paddle.com/paddle/v2/paddle.js';
        script.onload = () => {
            if (sandbox) {
                window.Paddle.Environment.set('sandbox');
            }

            window.Paddle.Initialize({ token });
            resolve(window.Paddle);
        };
        script.onerror = () => {
            ready = undefined;
            reject(new Error('Paddle.js could not be loaded.'));
        };
        document.head.appendChild(script);
    });

    return ready;
}

export default async function openPaddleCheckout(config, options) {
    const paddle = await loadPaddle(config);

    paddle.Checkout.open(options);
}
