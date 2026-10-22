<x-layouts::app title="Privacy" description="How GenDoc handles your data: documents stay in your browser, nothing is stored on our servers, and analytics are cookieless and anonymous.">
    <main class="mx-auto w-full max-w-2xl flex-1 px-4 py-10">
        <a href="{{ route('home') }}" class="text-sm font-medium text-blue-700 hover:underline">&larr; Back to {{ config('app.name') }}</a>

        <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-900">Privacy</h1>
        <p class="mt-2 text-slate-600">The short version: your documents are yours, and we don't keep them.</p>

        <div class="mt-8 space-y-6 text-slate-700 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-slate-900 [&_p]:mt-1">
            <section>
                <h2>Your documents stay in your browser</h2>
                <p>Everything you type, including your logo, is saved only in your own browser's storage on this device. There are no accounts, and we have no copy of your documents. Clearing your browser data deletes them, so export a JSON file if you want a backup.</p>
            </section>

            <section>
                <h2>What we send to our server, and when</h2>
                <p>While you edit, your document's details (but not your logo) are sent to our server so it can update the live preview. When you download a PDF or import a file, the whole document is sent so we can create the PDF or check the file. We use it for that request only and discard it straight away.</p>
            </section>

            <section>
                <h2>Nothing is logged or stored</h2>
                <p>We never write your documents to disk or to our logs. To stop abuse, we briefly count how many PDFs each IP address creates in a minute; that count expires after a minute. Like any website, our server keeps short-lived technical logs (such as which page was requested), which never contain your documents.</p>
            </section>

            <section>
                <h2>Analytics</h2>
                <p>We use our own self-hosted copy of Umami to count visits and how often PDFs are downloaded. It is cookieless and anonymous: it doesn't identify you or follow you across sites, and it never receives anything you type into a document.</p>
            </section>

            <section>
                <h2>Cookies</h2>
                <p>We set one cookie: an essential session cookie that keeps the editor working securely. The session it points to holds only a security token, never your documents. We don't use advertising or tracking cookies, so there's no cookie banner.</p>
            </section>
        </div>
    </main>
</x-layouts::app>
