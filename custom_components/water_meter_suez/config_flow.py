import voluptuous as vol
from homeassistant import config_entries
from homeassistant.const import CONF_API_KEY

from .const import DOMAIN  # Assure-toi que ce pointe vers le bon fichier const.py

class MyCustomIntegrationFlowHandler(config_entries.ConfigFlow, domain=DOMAIN):
    VERSION = 1
    CONNECTION_CLASS = config_entries.CONN_CLASS_CLOUD_PUSH

    async def async_step_user(self, user_input=None):
        """Handle a flow initiated by the user."""
        errors = {}

        if user_input is not None:
            # Validate the user input
            if len(user_input[CONF_API_KEY]) < 5:
                errors[CONF_API_KEY] = "Invalid API Key"
            else:
                return self.async_create_entry(
                    title="My Custom Integration",
                    data={CONF_API_KEY: user_input[CONF_API_KEY]},
                )

        # Show a form to the user
        return self.async_show_form(
            step_id="user",
            data_schema=vol.Schema(
                {
                    vol.Required(CONF_API_KEY, default=""): str,
                }
            ),
            errors=errors,
        )
