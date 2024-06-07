"""Sensor platform for Water Meter."""
from homeassistant.components.sensor import SensorEntity
from homeassistant.config_entries import ConfigEntry
from homeassistant.core import HomeAssistant
from homeassistant.helpers.update_coordinator import CoordinatorEntity

from . import DOMAIN

async def async_setup_entry(hass: HomeAssistant, entry: ConfigEntry, async_add_entities):
    """Set up the Water Meter sensor."""
    coordinator = hass.data[DOMAIN][entry.entry_id]

    async_add_entities([WaterMeterSensor(coordinator)])

class WaterMeterSensor(CoordinatorEntity, SensorEntity):
    """Water Meter sensor."""

    def __init__(self, coordinator):
        """Initialize the sensor."""
        super().__init__(coordinator)
        self._attr_name = "Water Meter Consumption"
        self._attr_unique_id = "water
