from homeassistant.config_entries import ConfigEntry
from homeassistant.core import HomeAssistant

DOMAIN = "Water_meter_suez"

async def async_setup(hass: HomeAssistant, config: dict):
    """Set up the My Custom Integration component."""
    # Not used in this example
    return True

async def async_setup_entry(hass: HomeAssistant, entry: ConfigEntry):
    """Set up my custom integration from a config entry."""
    # Your specific setup code goes here
    hass.async_create_task(
        hass.config_entries.async_forward_entry_setup(entry, "sensor")
    )
    return True

async def async_unload_entry(hass: HomeAssistant, entry: ConfigEntry):
    """Unload a config entry."""
    # Your specific unloading code goes here
    await hass.config_entries.async_forward_entry_unload(entry, "sensor")
    return True
