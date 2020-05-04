import { extend } from "flarum/extend";
import app from "flarum/app";
import HeaderSecondary from "flarum/components/HeaderSecondary";
import SettingsPage from "flarum/components/SettingsPage";
import LogInModal from "flarum/components/LogInModal";

app.initializers.add('dbkg-sso', () => {
    // remove sign up link from the modal
    LogInModal.prototype.footer = () => <div>Ihre Zugangsdaten entsprechen Ihren Zugangsdaten für "Mein Bereich".<br/>Falls Sie diese vergessen haben, können Sie <a style="font-weight: bold" href="https://meinbereich.dbkg.de">HIER</a> Ihr Passwort zurücksetzen.</div>

    // remove sign up button
    extend(HeaderSecondary.prototype, 'items', items => {
        if (items.has('signUp')) {
            items.remove('signUp');
        }
    });

    // remove change password and email settings
    extend(SettingsPage.prototype, 'accountItems', items => {
        items.remove('changeEmail');
        items.remove('changePassword');
    });

    // remove 'remove account' - section
    extend(SettingsPage.prototype, 'settingsItems', items => {
        if (items.has('account')
            && items.get('account').props.children.length === 0) {
            items.remove('account');
        }
    });

});
