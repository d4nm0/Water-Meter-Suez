import logging
import requests
import voluptuous as vol
from homeassistant.helpers.entity import Entity
from homeassistant.helpers import config_validation as cv

_LOGGER = logging.getLogger(__name__)

DOMAIN = "water_meter_suez"

CONFIG_SCHEMA = vol.Schema(
    {
        DOMAIN: vol.Schema(
            {vol.Required("api_key"): cv.string}
        )
    },
    extra=vol.ALLOW_EXTRA,
)

def setup_platform(hass, config, add_entities, discovery_info=None):
    """Set up the sensor platform."""
    api_key = config[DOMAIN]["api_key"]
    add_entities([CustomSensor(api_key)], True)

class CustomSensor(Entity):
    """Representation of a Sensor."""

    def __init__(self, api_key):
        """Initialize the sensor."""
        self._api_key = api_key
        self._state = None
        self._attributes = {}

    @property
    def name(self):
        """Return the name of the sensor."""
        return "Custom Sensor"

    @property
    def state(self):
        """Return the state of the sensor."""
        return self._state

    @property
    def device_state_attributes(self):
        """Return the state attributes."""
        return self._attributes

    def update(self):
        """Fetch new state data for the sensor."""
        url = "https://api.onconnect-coach.3slab.fr/v1/water-flow/api/monthly/period/2024/6/days?people=2&collectiveHotWater=0&outsideUse=1"
        headers = {
            'Authorization': 'Bearer ' + self._api_key
        }

        try:
            response = requests.get(url, headers=headers)
            response.raise_for_status()
            data = response.json()
            # Exemple de manipulation des données récupérées
            self._state = data["value"]
            self._attributes = {"timestamp": data["timestamp"]}
        except requests.exceptions.RequestException as ex:
            _LOGGER.error("Error fetching data: %s", ex)
            self._state = None
            self._attributes = {}
