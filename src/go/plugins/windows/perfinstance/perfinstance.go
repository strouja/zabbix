package perfinstance

import (
	"encoding/json"
	"errors"
	"fmt"
	"sync"
	"time"

	"zabbix.com/pkg/pdh"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/win32"
)

//Plugin -
type Plugin struct {
	plugin.Base
	refreshMux         sync.Mutex
	reloadMux          sync.RWMutex
	nextObjectRefresh  time.Time
	nextEngNameRefresh time.Time
}

var impl Plugin

//Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (response interface{}, err error) {
	if len(params) > 1 {
		return nil, errors.New("Too many parameters.")
	}

	if len(params) == 0 || params[0] == "" {
		return nil, errors.New("Invalid first parameter.")
	}

	if err = p.refreshObjects(); err != nil {
		return nil, fmt.Errorf("Cannot refresh objects: %s", err.Error())
	}

	var name string
	switch key {
	case "perf_instance.discovery":
		name = params[0]
	case "perf_instance_en.discovery":
		if name = p.getLocalName(params[0]); name == "" {
			if err = p.reloadEngObjectNames(); err != nil {
				return nil, fmt.Errorf("Cannot reload English names: %s", err.Error())
			}
			if name = p.getLocalName(params[0]); name == "" {
				return nil, errors.New("Cannot get local name")
			}
		}
	default:
		return nil, plugin.UnsupportedMetricError
	}

	var instances []win32.Instance
	if instances, err = win32.PdhEnumObjectItems(name); err != nil {
		return nil, fmt.Errorf("Cannot get instances list: %s", err.Error())
	}

	if len(instances) < 1 {
		return "[]", nil
	}

	var respJSON []byte
	if respJSON, err = json.Marshal(instances); err != nil {
		return
	}

	return string(respJSON), nil
}

func init() {
	plugin.RegisterMetrics(&impl, "WindowsPerfInstance",
		"perf_instance.discovery", "Get Windows performance instance object list.",
		"perf_instance_en.discovery", "Get Windows performance instance object English list.",
	)
}

func (p *Plugin) refreshObjects() (err error) {
	p.refreshMux.Lock()
	defer p.refreshMux.Unlock()
	if time.Now().After(p.nextObjectRefresh) {
		_, err = win32.PdhEnumObject()
		p.nextObjectRefresh = time.Now().Add(time.Minute)
	}

	return
}

func (p *Plugin) reloadEngObjectNames() (err error) {
	p.reloadMux.Lock()
	defer p.reloadMux.Unlock()
	if time.Now().After(p.nextEngNameRefresh) {
		err = pdh.LocateObjectsAndDefaultCounters(false)
		p.nextEngNameRefresh = time.Now().Add(time.Minute)
	}

	return
}

func (p *Plugin) getLocalName(engName string) string {
	p.reloadMux.RLock()
	defer p.reloadMux.RUnlock()
	if name, ok := pdh.ObjectsNames[engName]; ok {
		return name
	}

	return ""
}
