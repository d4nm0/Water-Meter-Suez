"""The Water Meter integration."""
import asyncio
from datetime import timedelta
import requests
import logging

from homeassistant.config_entries import ConfigEntry
from homeassistant.core import HomeAssistant
from homeassistant.helpers.update_coordinator import DataUpdateCoordinator, UpdateFailed

_LOGGER = logging.getLogger(__name__)

DOMAIN = "water_meter"

async def async_setup_entry(hass: HomeAssistant, entry: ConfigEntry):
    """Set up Water Meter from a config entry."""
    coordinator = WaterMeterDataUpdateCoordinator(hass, entry)
    await coordinator.async_config_entry_first_refresh()

    hass.data.setdefault(DOMAIN, {})[entry.entry_id] = coordinator

    hass.config_entries.async_setup_platforms(entry, ["sensor"])

    return True

class WaterMeterDataUpdateCoordinator(DataUpdateCoordinator):
    """Class to manage fetching data from the Water Meter."""

    def __init__(self, hass: HomeAssistant, entry: ConfigEntry):
        """Initialize the coordinator."""
        self.entry = entry
        super().__init__(
            hass,
            _LOGGER,
            name=DOMAIN,
            update_interval=timedelta(hours=1),  # Change the update interval as needed
        )

    async def _async_update_data(self):
        """Fetch data from the Water Meter."""
        try:
            return await hass.async_add_executor_job(get_water_meter_data, self.entry)
        except Exception as err:
            raise UpdateFailed(f"Error fetching data: {err}") from err

def get_water_meter_data(entry: ConfigEntry):
    """Fetch water meter data from the API."""
    url = "https://api.onconnect-coach.3slab.fr/v1/water-flow/api/monthly/period/2024/6/days?people=2&collectiveHotWater=0&outsideUse=1"
    headers = {
        'Authorization': f"Bearer {entry.data['api_token']}"
    }
    response = requests.get(url, headers=headers)
    response.raise_for_status()
    data = response.json()
    
    # Extract total volumes for the periods
    total_volumes = [period['consumption']['totalVolume'] for period in data['periods']]
    
    return {
        "total_volumes": total
