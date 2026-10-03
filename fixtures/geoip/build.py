# Writes geoip.mmdb from countries.json: pip install mmdb-writer netaddr && python3 build.py
import json
from pathlib import Path

from mmdb_writer import MMDBWriter
from netaddr import IPSet

here = Path(__file__).parent
writer = MMDBWriter(ip_version=4, database_type='GeoIP2-City', languages=['en'], description={'en': 'OpenDXP test addresses'})

for iso, country in json.loads((here / 'countries.json').read_text()).items():
    writer.insert_network(IPSet([country['address'] + '/32']), {
        'continent': {'code': country['continent']},
        'country': {'iso_code': iso, 'names': {'en': country['name']}},
        'location': {'latitude': country['latitude'], 'longitude': country['longitude']},
    })

writer.to_db_file(str(here / 'geoip.mmdb'))
