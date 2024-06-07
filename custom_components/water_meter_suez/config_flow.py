import voluptuous as vol
from homeassistant import config_entries

from .const import DOMAIN

class WaterMeterConfigFlow(config_entries.ConfigFlow, domain=DOMAIN):
    async def async_step_user(self, user_input=None):
        errors = {}

        if user_input is not None:
            # Vous pouvez ajouter ici des vérifications supplémentaires sur le token API si nécessaire
            return self.async_create_entry(title="Water Meter", data=user_input)

        return self.async_show_form(
            step_id="user",
            data_schema=vol.Schema({
                vol.Required("api_token"): str
            }),
            errors=errors,
        )
