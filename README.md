# Over Zakenregister

Het Zakenregister benut de kracht van OpenRegisters om gemakkelijk en snel alle door VNG gedefinieerde ZGW API's te leveren. Het ondersteunt het extend patroon op alle onderliggende objecten en maakt data minimalisatie mogelijk door middel van filtering, waardoor een efficiënte en flexibele oplossing voor zaakgericht werken binnen de overheid wordt geboden. Het Zakenregister probeert zo goed mogelijk de laatste versie van ZGW te ondersteunen (op dit moment 1.5.1).

## Kernvoordelen

* **Uniforme Toegang:** Biedt één oplossing voor alle ZGW API's, wat zorgt voor een gestroomlijnde integratie en gebruiksgemak.
* **Flexibiliteit:** Ondersteunt het extend patroon op alle onderliggende objecten, wat aanpassing en uitbreiding van functionaliteiten mogelijk maakt.
* **Data Minimalisatie:** Maakt efficiënt gebruik van data mogelijk door filtering, wat bijdraagt aan privacybescherming en performance.
* **Snelheid:** Profiteert van de snelheid en schaalbaarheid van OpenRegisters voor snelle toegang en verwerking van zaken.

## Gebaseerd op Open Index

Doordat

## Installatie

Het Zakenregister is ontworpen voor eenvoudige lokale installatie voor ontwikkelingsdoeleinden of voor implementatie online op een server of Kubernetes/Haven platform. Meer informatie over de installatie vindt u in [INSTALLATION.md](INSTALLATION.md).

## Bijdragen

Interesse om bij te dragen aan het Zakenregister? Of het nu gaat om het melden van bugs, het voorstellen van nieuwe functies of het indienen van codewijzigingen, wij verwelkomen uw bijdrage. Raadpleeg onze [CONTRIBUTING.md](CONTRIBUTING.md) voor meer details over hoe u kunt bijdragen.

## Licentie

Het Zakenregister is uitgegeven onder een EUPL 1.2 licentie. Voor meer details, zie het [LICENSE.md](LICENSE.md) bestand in onze GitHub repository.

## Contact

Voor meer informatie over het Zakenregister en hoe u dit binnen uw organisatie kunt implementeren, neem contact met ons op via <info@conduction.nl>.

## Notities installatie CommonGateway

* Voorlopige fix Catalogus endpoint voor OpenFormulieren: Zorg ervoor dat gebruikte application een configuration veld heeft als:
  `"configuration": [
        {
            "https://vng.opencatalogi.nl/EntityEndpoint/Catalogus.endpoint.json": {
                "out": {
                    "body": {
                        "mapping": "https://vng.opencatalogi.nl/schemas/zgw.catalogusContext.mapping.json"
                    }
                }
            }
        }`
