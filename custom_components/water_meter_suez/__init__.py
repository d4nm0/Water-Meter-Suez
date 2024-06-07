"""The Water Meter integration."""
import asyncio
from datetime import timedelta
import logging

from homeassistant.config_entries import ConfigEntry
from homeassistant.core import HomeAssistant

from .config_flow import WaterMeterConfigFlow
from .coordinator import WaterMeterDataUpdateCoordinator

_LOGGER = logging.getLogger(__name__)

DOMAIN = "water_meter"

async def async_setup(hass: HomeAssistant, config: dict):
    """Set up the Water Meter integration."""
    hass.data.setdefault(DOMAIN, {})
    return True

async def async_setup_entry(hass: HomeAssistant, entry: ConfigEntry):
    """Set up Water Meter from a config entry."""
    coordinator = WaterMeterDataUpdateCoordinator(hass, entry)
    await coordinator.async_config_entry_first_refresh()

    hass.data.setdefault(DOMAIN, {})[entry.entry_id] = coordinator

    hass.config_entries.async_setup_platforms(entry, ["sensor"])

    return True

async def async_unload_entry(hass: HomeAssistant, entry: ConfigEntry):
    """Unload a config entry."""
    unload_ok = await hass.config_entries.async_unload_platforms(entry, ["sensor"])
    if unload_ok:
        hass.data[DOMAIN].pop(entry.entry_id)

    return unload_ok

async def async_migrate_entry(hass: HomeAssistant, entry):
    """Migrate old entry data format."""
    version = entry.version

    _LOGGER.debug("Migrating from version %s", version)

    # Migrate from version 1 to version 2
    if version == 1:
        # Migration code here if needed
        entry.version = 2
        entry.data = {
            **entry.data,
            # Add any additional fields needed for version 2
        }

    return True
